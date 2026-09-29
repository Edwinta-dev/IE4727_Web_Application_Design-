#!/usr/bin/env python3
"""Supervised, unattended coding-agent runner over GitHub issues.

A generalised, cross-platform, agent-agnostic descendant of a Codex-specific
supervisor. One agent worker per issue. The agent edits the working tree and
runs local checks, but it does NOT own git/GitHub mutations. This Python
supervisor independently re-validates the change before committing and commits
one issue at a time. It never pushes or opens a PR unless asked.

Branching (solo workflow, see AGENTS.md section 6):
    * branch_mode=auto: if a non-base branch is checked out, continue on it;
      otherwise continue work_branch (local, then origin), else create it
      from origin/<base>. A work branch already merged into base is
      fast-forwarded, and one that is behind base has base merged in (the
      merge is aborted and the run stops on any conflict).
    * close_on=merge: commits carry "Closes #N" and the issue closes when the
      PR is merged. Issues already committed on the branch are skipped.
    * Failed partial work is kept as a tag deferred/issue-<N>, not a stash or
      a branch; restore it with `git stash apply deferred/issue-<N>`.

Which issue is worked next is chosen by DETERMINISTIC rules:
    * issue-number RANGE            (--min-issue / --max-issue)
    * LABEL filters                 (--labels a,b  ;  --labels-all to require all)
    * MILESTONE filter              (--milestone "M1")
    * explicit EXCLUDES             (--exclude 35,36)
    * ascending issue-number SORT   (lowest eligible open issue first)

Everything project-specific is configuration, not code:
    * the agent CLI (codex, claude, aider, or any command) -> --agent / agents.json
    * what "validate" means for this repo                 -> --validate validate.json
    * the safety contract text handed to the agent        -> --contract-file

    # Zero-repeat: put repo/agent/model/validate/filters in a project config
    # (issue-automation.config.json) once, then just:
    py automation/run_issues.py      # finds automation/issue-automation.config.json
    python run_issues.py --config path/to/issue-automation.config.json

    # Any flag still overrides the config for a one-off:
    python run_issues.py --max-issue 20 --push

    python run_issues.py --self-test          # offline unit checks
    python run_issues.py --resume-latest      # continue an interrupted run

Requirements: Python 3.10+, git, gh (authenticated), and the chosen agent CLI.
PyYAML is optional (only for .yaml config files).
"""

from __future__ import annotations

import argparse
import datetime as dt
import json
import os
import queue
import re
import shutil
import signal
import subprocess
import sys
import tempfile
import threading
import time
from dataclasses import dataclass, asdict, field
from pathlib import Path
from typing import Optional, Sequence


# ===========================================================================
# Constants / small helpers
# ===========================================================================

APP_DIR_NAME = "issue-runner"
RUNS_DIR_NAME = "runs"
STATE_FILE_NAME = "supervisor_state.json"
SUMMARY_FILE_NAME = "SUPERVISOR_SUMMARY.md"

# The agent must end its transcript with a bounded result block. Sentinels are
# overridable per agent; these are the defaults.
DEFAULT_RESULT_BEGIN = "===AGENT_ISSUE_RESULT_BEGIN==="
DEFAULT_RESULT_END = "===AGENT_ISSUE_RESULT_END==="

IS_WINDOWS = os.name == "nt"


class SupervisorError(RuntimeError):
    pass


def now_local() -> dt.datetime:
    return dt.datetime.now().astimezone()


def iso_now() -> str:
    return now_local().isoformat(timespec="seconds")


def print_err(message: str) -> None:
    print(f"\nERROR: {message}", file=sys.stderr, flush=True)


def find_executable(*names: str) -> Optional[str]:
    for name in names:
        found = shutil.which(name)
        if found:
            return found
    return None


def run_capture(args: Sequence[str], *, cwd: Optional[Path] = None,
                input_text: Optional[str] = None, check: bool = True,
                timeout: Optional[float] = None) -> subprocess.CompletedProcess:
    cp = subprocess.run(
        [str(x) for x in args],
        cwd=str(cwd) if cwd else None,
        input=input_text,
        text=True, encoding="utf-8", errors="replace",
        stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
        shell=False, timeout=timeout,
    )
    if check and cp.returncode != 0:
        raise SupervisorError(
            f"Command failed ({cp.returncode}): {' '.join(map(str, args))}\n{cp.stdout}")
    return cp


def git(repo: Path, git_exe: str, *args: str, check: bool = True) -> str:
    return run_capture([git_exe, *args], cwd=repo, check=check).stdout


def gh(repo: Path, gh_exe: str, *args: str, check: bool = True) -> str:
    return run_capture([gh_exe, *args], cwd=repo, check=check).stdout


def get_dirty_status(repo: Path, git_exe: str) -> str:
    return git(repo, git_exe, "status", "--porcelain").rstrip()


def get_changed_files(repo: Path, git_exe: str) -> list[str]:
    tracked = [x.strip() for x in
               git(repo, git_exe, "diff", "--name-only", "HEAD").splitlines() if x.strip()]
    untracked = [x.strip() for x in
                 git(repo, git_exe, "ls-files", "--others", "--exclude-standard").splitlines()
                 if x.strip()]
    return sorted(set(tracked + untracked))


def tail_text(path: Path, max_chars: int = 8000) -> str:
    try:
        return path.read_text(encoding="utf-8", errors="replace")[-max_chars:]
    except FileNotFoundError:
        return ""


def safe_write_json(path: Path, obj: object) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(path.suffix + ".tmp")
    tmp.write_text(json.dumps(obj, indent=2, ensure_ascii=False), encoding="utf-8")
    os.replace(tmp, path)


def glob_to_regex(pattern: str) -> str:
    """Translate a path glob into an anchored regex.

    Supports gitignore-style globstar: `**/` matches zero or more directories,
    `**` matches anything, `*` matches within a segment, `?` one char. This is
    more reliable than pathlib.PurePath.match, whose `**` support varies by
    Python version and does not let `**/` match zero leading segments.
    """
    out = ["^"]
    i, n = 0, len(pattern)
    while i < n:
        c = pattern[i]
        if pattern.startswith("**/", i):
            out.append("(?:.*/)?")
            i += 3
        elif pattern.startswith("**", i):
            out.append(".*")
            i += 2
        elif c == "*":
            out.append("[^/]*")
            i += 1
        elif c == "?":
            out.append("[^/]")
            i += 1
        else:
            out.append(re.escape(c))
            i += 1
    out.append("$")
    return "".join(out)


def glob_match(path: str, pattern: str) -> bool:
    return re.match(glob_to_regex(pattern), path.replace("\\", "/")) is not None


def load_config_file(path: Path) -> dict:
    text = path.read_text(encoding="utf-8")
    if path.suffix.lower() in (".yaml", ".yml"):
        try:
            import yaml  # type: ignore
        except ModuleNotFoundError as exc:
            raise SupervisorError(
                f"{path.name} is YAML but PyYAML is not installed; use JSON.") from exc
        data = yaml.safe_load(text)
    else:
        data = json.loads(text)
    if not isinstance(data, dict):
        raise SupervisorError(f"{path.name}: top level must be an object.")
    return data


# ===========================================================================
# Portable runs directory + process-tree kill
# ===========================================================================

def runs_root() -> Path:
    if IS_WINDOWS:
        base = os.environ.get("LOCALAPPDATA") or str(Path.home() / "AppData" / "Local")
    else:
        base = os.environ.get("XDG_STATE_HOME") or str(Path.home() / ".local" / "state")
    return Path(base) / APP_DIR_NAME / RUNS_DIR_NAME


def popen_worker(args: Sequence[str], repo: Path):
    """Start the agent so its whole process tree can later be killed."""
    kwargs = dict(
        cwd=str(repo), stdin=subprocess.PIPE, stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT, text=True, encoding="utf-8", errors="replace",
        bufsize=1, shell=False,
    )
    if IS_WINDOWS:
        kwargs["creationflags"] = getattr(subprocess, "CREATE_NEW_PROCESS_GROUP", 0)
    else:
        kwargs["start_new_session"] = True   # own process group for killpg
    return subprocess.Popen([str(a) for a in args], **kwargs)


def kill_process_tree(proc: subprocess.Popen) -> None:
    try:
        if IS_WINDOWS:
            taskkill = find_executable("taskkill.exe", "taskkill")
            if taskkill:
                run_capture([taskkill, "/PID", str(proc.pid), "/T", "/F"],
                            check=False, timeout=20)
        else:
            os.killpg(os.getpgid(proc.pid), signal.SIGKILL)
    except (ProcessLookupError, PermissionError, OSError):
        pass


# ===========================================================================
# Tool discovery
# ===========================================================================

@dataclass
class Tools:
    git: str
    gh: str
    python: str


def discover_core_tools() -> Tools:
    git_exe = find_executable("git.exe", "git")
    gh_exe = find_executable("gh.exe", "gh")
    missing = [n for n, v in (("git", git_exe), ("gh", gh_exe)) if not v]
    if missing:
        raise SupervisorError("Required executable(s) not on PATH: " + ", ".join(missing))
    return Tools(git=git_exe or "", gh=gh_exe or "", python=sys.executable)


# ===========================================================================
# Agent adapter  (the generalisation of the codex-specific worker command)
# ===========================================================================

@dataclass
class AgentSpec:
    """How to invoke an arbitrary coding-agent CLI.

    argv is a template list. These tokens are substituted at launch:
        {EXE}         resolved agent executable
        {MODEL}       --model value (token removed if no model configured)
        {PROMPT_FILE} path to a temp file holding the prompt
        {LAST_MSG}    path the agent may write its final message to
    prompt_mode:
        "stdin" -> prompt written to the process stdin
        "file"  -> prompt written to {PROMPT_FILE}; reference it in argv
        "arg"   -> prompt substituted for a literal {PROMPT} token in argv
    """
    name: str
    exe_candidates: list[str]
    argv: list[str]
    prompt_mode: str = "stdin"
    result_begin: str = DEFAULT_RESULT_BEGIN
    result_end: str = DEFAULT_RESULT_END
    model: Optional[str] = None       # default model this agent uses
    prompt_prefix: str = ""           # custom text injected near the top of every prompt
    prompt_suffix: str = ""           # custom text injected before the result contract


BUILTIN_AGENTS: dict[str, AgentSpec] = {
    # Tested-shape preset for OpenAI Codex CLI (`codex exec --json`).
    "codex": AgentSpec(
        name="codex",
        exe_candidates=["codex.cmd", "codex.exe", "codex"],
        argv=["{EXE}", "--model", "{MODEL}", "--ask-for-approval", "never",
              "exec", "--sandbox", "workspace-write", "--json",
              "--output-last-message", "{LAST_MSG}", "-"],
        prompt_mode="stdin",
    ),
    # Anthropic Claude Code CLI, headless. Adjust flags to your install/policy.
    "claude": AgentSpec(
        name="claude",
        exe_candidates=["claude.cmd", "claude.exe", "claude"],
        argv=["{EXE}", "--model", "{MODEL}", "--print",
              "--permission-mode", "acceptEdits"],
        prompt_mode="stdin",
    ),
    # aider, non-interactive single message.
    "aider": AgentSpec(
        name="aider",
        exe_candidates=["aider"],
        argv=["{EXE}", "--model", "{MODEL}", "--yes", "--no-auto-commits",
              "--message-file", "{PROMPT_FILE}"],
        prompt_mode="file",
    ),
}


def _read_prompt_field(data: dict, key: str) -> str:
    """A prompt_prefix/prompt_suffix may be inline text, or {"file": "path"}."""
    val = data.get(key, "")
    if isinstance(val, dict) and "file" in val:
        return Path(val["file"]).read_text(encoding="utf-8")
    return str(val or "")


def resolve_agent(name_or_config: str) -> tuple[AgentSpec, str]:
    """Return (spec, resolved_exe). name_or_config is a builtin name or a config path."""
    if name_or_config in BUILTIN_AGENTS:
        spec = BUILTIN_AGENTS[name_or_config]
    else:
        path = Path(name_or_config)
        if not path.exists():
            raise SupervisorError(
                f"Unknown agent '{name_or_config}'. Use a builtin "
                f"({', '.join(BUILTIN_AGENTS)}) or a path to an agent config file.")
        data = load_config_file(path)
        spec = AgentSpec(
            name=str(data.get("name", path.stem)),
            exe_candidates=list(data["exe_candidates"]),
            argv=list(data["argv"]),
            prompt_mode=str(data.get("prompt_mode", "stdin")),
            result_begin=str(data.get("result_begin", DEFAULT_RESULT_BEGIN)),
            result_end=str(data.get("result_end", DEFAULT_RESULT_END)),
            model=(str(data["model"]) if data.get("model") else None),
            prompt_prefix=_read_prompt_field(data, "prompt_prefix"),
            prompt_suffix=_read_prompt_field(data, "prompt_suffix"),
        )
    exe = find_executable(*spec.exe_candidates)
    if not exe:
        raise SupervisorError(
            f"Agent '{spec.name}' executable not found (tried: "
            f"{', '.join(spec.exe_candidates)}).")
    return spec, exe


def build_agent_argv(spec: AgentSpec, exe: str, model: Optional[str],
                     prompt_file: Path, last_msg: Path, prompt: str) -> list[str]:
    subs = {"{EXE}": exe, "{MODEL}": model or "",
            "{PROMPT_FILE}": str(prompt_file), "{LAST_MSG}": str(last_msg),
            "{PROMPT}": prompt}
    out: list[str] = []
    skip_next = False
    for i, tok in enumerate(spec.argv):
        if skip_next:
            skip_next = False
            continue
        # Drop a "--model {MODEL}" pair entirely when no model is configured.
        if tok == "--model" and not model:
            skip_next = True
            continue
        out.append(subs.get(tok, tok))
    return out


# ===========================================================================
# Persistent state
# ===========================================================================

@dataclass
class RunState:
    repo_path: str
    repo_name: str
    branch: str
    run_dir: str
    frozen_numbers: list[int]
    frozen_titles: dict[str, str]
    started_at: str
    attempt: int = 0
    retry_counts: dict[str, int] = field(default_factory=dict)
    deferred: dict[str, str] = field(default_factory=dict)
    previous_failure: dict[str, str] = field(default_factory=dict)
    current_issue: Optional[int] = None
    status: str = "running"
    base_branch: str = "main"

    @property
    def state_path(self) -> Path:
        return Path(self.run_dir) / STATE_FILE_NAME

    def save(self) -> None:
        safe_write_json(self.state_path, asdict(self))

    @classmethod
    def load(cls, path: Path) -> "RunState":
        return cls(**json.loads(path.read_text(encoding="utf-8")))


def find_latest_resumable_state(root: Path, repo: Optional[Path] = None,
                                branch: str = "") -> Path:
    candidates: list[tuple[float, Path]] = []
    if root.exists():
        for sp in root.glob(f"*/{STATE_FILE_NAME}"):
            try:
                data = json.loads(sp.read_text(encoding="utf-8"))
                same_repo = (repo is None or
                             Path(data.get("repo_path", "")).resolve() == repo.resolve())
                if (same_repo and (not branch or data.get("branch") == branch)
                        and (branch or data.get("status") in
                             {"running", "stopped", "interrupted"})):
                    candidates.append((sp.stat().st_mtime, sp))
            except Exception:
                continue
    if not candidates:
        raise SupervisorError(f"No resumable run found under {root} for this repository/branch")
    candidates.sort(reverse=True)
    if branch:
        latest = json.loads(candidates[0][1].read_text(encoding="utf-8"))
        if latest.get("status") not in {"running", "stopped", "interrupted"}:
            raise SupervisorError(
                f"Latest run for {branch} is finished. Edit the config and start a new run.")
    return candidates[0][1]


# ===========================================================================
# GitHub issue queue  (RANGE + LABELS + MILESTONE filters, ascending sort)
# ===========================================================================

@dataclass(frozen=True)
class Issue:
    number: int
    title: str


@dataclass
class Filters:
    min_issue: int
    max_issue: int
    milestone: str
    labels: list[str]
    labels_all: bool
    exclude: set[int]


def get_repo_name(repo: Path, tools: Tools) -> str:
    out = gh(repo, tools.gh, "repo", "view", "--json", "nameWithOwner",
             "-q", ".nameWithOwner").strip()
    if not out:
        raise SupervisorError("Could not determine GitHub repository.")
    return out


def _issue_matches(item: dict, f: Filters) -> bool:
    number = int(item["number"])
    if number in f.exclude:
        return False
    if f.min_issue > 0 and number < f.min_issue:
        return False
    if f.max_issue > 0 and number > f.max_issue:
        return False
    if f.milestone:
        ms = (item.get("milestone") or {}).get("title") or ""
        if ms != f.milestone:
            return False
    if f.labels:
        have = {str(x.get("name", "")) for x in item.get("labels", [])}
        wanted = set(f.labels)
        if f.labels_all:
            if not wanted.issubset(have):
                return False
        elif have.isdisjoint(wanted):
            return False
    return True


def list_matching_issues(repo: Path, tools: Tools, repo_name: str,
                         f: Filters, limit: int) -> list[Issue]:
    raw = gh(repo, tools.gh, "issue", "list", "--repo", repo_name,
             "--state", "open", "--limit", str(limit),
             "--json", "number,title,milestone,labels")
    data = json.loads(raw or "[]")
    result = [Issue(int(x["number"]), str(x["title"]))
              for x in data if _issue_matches(x, f)]
    return sorted(result, key=lambda x: x.number)   # ascending = execution order


def get_open_frozen_issues(repo: Path, tools: Tools, repo_name: str,
                           state: RunState, exclude: set[int]) -> list[Issue]:
    raw = gh(repo, tools.gh, "issue", "list", "--repo", repo_name,
             "--state", "open", "--limit", "1000", "--json", "number,title")
    open_numbers = {int(x["number"]) for x in json.loads(raw or "[]")}
    # With close_on=merge an issue stays open until its PR merges; skip it here.
    done = issues_committed_on_branch(repo, tools.git, state.base_branch)
    return [Issue(n, state.frozen_titles[str(n)])
            for n in state.frozen_numbers
            if n in open_numbers and n not in exclude and n not in done]


def get_issue_text(repo: Path, tools: Tools, repo_name: str, number: int) -> str:
    item = json.loads(gh(repo, tools.gh, "issue", "view", str(number),
                         "--repo", repo_name, "--json",
                         "number,title,body,labels,milestone"))
    labels = ", ".join(str(x.get("name", "")) for x in item.get("labels", []))
    ms = (item.get("milestone") or {}).get("title") or ""
    return (f"# {item['number']} {item['title']}\n\n{item.get('body') or ''}\n\n"
            f"Labels: {labels}\nMilestone: {ms}\n")


# ===========================================================================
# Prompt + agent result protocol
# ===========================================================================

DEFAULT_CONTRACT = """\
Operating contract for the unattended coding agent:
- Implement EXACTLY the one issue described. Make only the change it specifies.
- Repository reality wins over stale specs, comments, or screenshots. Inspect
  the existing code before editing; prefer the current architecture over a
  speculative rewrite.
- Do not perform opportunistic refactors, unrelated renames, dependency bumps,
  or architecture migrations.
- Reuse existing modules, utilities, and conventions where practical.
- Tests are required. Run focused tests for what you changed plus the repo's
  existing lint/build/test for the areas you touched.
- Verify behaviour programmatically; do not claim success without evidence.
- Do not weaken, skip, or delete tests to make a check pass.
"""


def make_prompt(repo_name: str, issue: Issue, issue_text: str,
                contract: str, spec: AgentSpec,
                dirty_status: str, previous_failure: str) -> str:
    dirty = (f"There are uncommitted changes from a previous attempt:\n\n"
             f"{dirty_status}\n\nInspect them; preserve valid partial work for "
             f"THIS issue. Do not blindly reset, clean, or discard.\n"
             if dirty_status else "The working tree is clean at the start.\n")
    previous = (f"PREVIOUS ATTEMPT / VALIDATION FEEDBACK:\n\n{previous_failure}\n\n"
                f"Address this directly; do not repeat the failing approach.\n"
                if previous_failure else "")
    prefix = (f"================ PROJECT INSTRUCTIONS ================\n"
              f"{spec.prompt_prefix.strip()}\n\n" if spec.prompt_prefix.strip() else "")
    suffix = (f"================ ADDITIONAL INSTRUCTIONS ================\n"
              f"{spec.prompt_suffix.strip()}\n\n" if spec.prompt_suffix.strip() else "")
    return f"""\
You are a coding agent working NON-INTERACTIVELY in repository {repo_name}.

You are implementing EXACTLY ONE issue:
#{issue.number} {issue.title}

{prefix}================ CONTEXT READING (do this first) ================
Read, if present and in this order: AGENTS.md, CLAUDE.md, README, and any spec
or test files directly relevant to this issue. Treat repository-wide agent
instructions as binding regardless of which tool they name.

================ ISSUE ================
{issue_text}

================ CONTRACT ================
{contract.strip()}

================ OPERATING BOUNDARY ================
The Python supervisor owns ALL git and GitHub mutations. You MUST NOT:
- create/switch/delete branches; git add/commit/reset --hard/clean/stash/rebase/
  merge/push/pull/fetch
- create/close/edit GitHub issues or pull requests, or use `gh` for network ops
- modify files outside this repository
You MAY: read git state, read/edit repo files for this issue, run tests, linters,
builds, formatters, type checks.

{dirty}
{previous}
{suffix}================ FINAL RESPONSE CONTRACT ================
At the VERY END, output exactly one bounded block:

{spec.result_begin}
STATUS: COMPLETE | BLOCKED | INCOMPLETE
VALIDATION: PASS | FAIL
ISSUE: #{issue.number}
SUMMARY: one concise line
TESTS: concise semicolon-separated checks actually run
NOTES: concise remaining risk/conflict, or NONE
{spec.result_end}

Use STATUS: COMPLETE and VALIDATION: PASS only when the work is truly ready for
the supervisor's independent validation and commit.
"""


@dataclass
class AgentResult:
    status: str
    validation: str
    raw: str


def parse_agent_result(text: str, spec: AgentSpec) -> AgentResult:
    starts = [m.start() for m in re.finditer(re.escape(spec.result_begin), text)]
    if starts:
        block = text[starts[-1]:]
        end = block.find(spec.result_end)
        if end >= 0:
            block = block[: end + len(spec.result_end)]
    else:
        block = text
    sm = re.search(r"(?im)^\s*STATUS:\s*(COMPLETE|BLOCKED|INCOMPLETE)\s*$", block)
    vm = re.search(r"(?im)^\s*VALIDATION:\s*(PASS|FAIL)\s*$", block)
    return AgentResult(status=sm.group(1).upper() if sm else "",
                       validation=vm.group(1).upper() if vm else "", raw=block)


# ===========================================================================
# Worker process / streaming with watchdog
# ===========================================================================

@dataclass
class WorkerOutcome:
    returncode: int
    reason: str
    output_text: str


def _reader_thread(pipe, q: "queue.Queue[Optional[str]]", log_file) -> None:
    try:
        for line in iter(pipe.readline, ""):
            log_file.write(line)
            log_file.flush()
            q.put(line)
    finally:
        q.put(None)


def humanize_event(line: str) -> Optional[str]:
    line = line.rstrip()
    if not line:
        return None
    try:
        evt = json.loads(line)
    except json.JSONDecodeError:
        return line if len(line) < 900 else None
    if not isinstance(evt, dict):
        return None
    typ = evt.get("type")
    if typ == "item.completed":
        item = evt.get("item") or {}
        if item.get("type") == "agent_message" and item.get("text"):
            return str(item["text"])
        if item.get("type") == "command_execution" and item.get("command"):
            return f"  [command] {item['command']}"
    if typ == "error":
        return f"[agent error] {evt.get('message', evt)}"
    return None


def run_agent_worker(*, repo: Path, spec: AgentSpec, exe: str, model: Optional[str],
                     prompt: str, log_path: Path, prompt_path: Path, last_path: Path,
                     stall_minutes: int, max_session_minutes: int,
                     heartbeat_seconds: int, issue: Issue, attempt: int) -> WorkerOutcome:
    prompt_path.write_text(prompt, encoding="utf-8")
    argv = build_agent_argv(spec, exe, model, prompt_path, last_path, prompt)

    proc = popen_worker(argv, repo)
    assert proc.stdin is not None and proc.stdout is not None

    if spec.prompt_mode == "stdin":
        proc.stdin.write(prompt)
    proc.stdin.close()

    q: "queue.Queue[Optional[str]]" = queue.Queue()
    start = last_output = time.monotonic()
    last_heartbeat = 0.0
    reason = "running"
    collected: list[str] = []

    with log_path.open("w", encoding="utf-8", errors="replace") as log_file:
        reader = threading.Thread(target=_reader_thread,
                                  args=(proc.stdout, q, log_file), daemon=True)
        reader.start()
        print(f"[{now_local():%H:%M:%S}] attempt {attempt} | issue #{issue.number} "
              f"| {spec.name} PID {proc.pid}", flush=True)

        reader_finished = False
        while proc.poll() is None:
            try:
                item = q.get(timeout=1.0)
                if item is None:
                    reader_finished = True
                else:
                    collected.append(item)
                    last_output = time.monotonic()
                    pretty = humanize_event(item)
                    if pretty:
                        print(pretty, flush=True)
            except queue.Empty:
                pass

            elapsed = time.monotonic() - start
            silent = time.monotonic() - last_output
            if elapsed >= max_session_minutes * 60:
                reason = f"worker exceeded {max_session_minutes} min session cap"
                print(f"Watchdog: {reason}; killing worker.", flush=True)
                kill_process_tree(proc)
                break
            if elapsed >= 60 and silent >= stall_minutes * 60:
                reason = f"no output for {stall_minutes} minutes"
                print(f"Watchdog: {reason}; killing worker.", flush=True)
                kill_process_tree(proc)
                break
            if elapsed - last_heartbeat >= heartbeat_seconds:
                print(f"[{now_local():%H:%M:%S}] issue #{issue.number} | "
                      f"runtime {dt.timedelta(seconds=int(elapsed))} | "
                      f"last output {int(silent)}s ago", flush=True)
                last_heartbeat = elapsed

        try:
            proc.wait(timeout=10)
        except subprocess.TimeoutExpired:
            kill_process_tree(proc)
            proc.wait(timeout=10)

        deadline = time.monotonic() + 3
        while time.monotonic() < deadline:
            try:
                item = q.get_nowait()
            except queue.Empty:
                if reader_finished:
                    break
                time.sleep(0.05)
                continue
            if item is None:
                reader_finished = True
            else:
                collected.append(item)

    rc = proc.returncode if proc.returncode is not None else -999
    if reason == "running":
        reason = "worker exited normally" if rc == 0 else f"worker exited with code {rc}"
    return WorkerOutcome(returncode=rc, reason=reason, output_text="".join(collected))


# ===========================================================================
# Usage-limit detection + wait-for-reset
# ===========================================================================

def _looks_like_blocking_usage_message(text: str) -> bool:
    lower = text.lower().strip()
    if not lower:
        return False
    if "approaching" in lower and "limit" in lower and not any(
            x in lower for x in ("reached", "exceeded", "too many requests")):
        return False
    explicit = (
        "reached your usage limit", "reached your rate limit",
        "usage limit reached", "rate limit reached",
        "usage limit exceeded", "rate limit exceeded",
        "quota exceeded", "too many requests",
        "http 429", "status 429", "error 429",
    )
    return any(p in lower for p in explicit)


def detect_usage_limit(text: str, *, worker_returncode: Optional[int] = None) -> bool:
    for line in text.splitlines():
        line = line.strip()
        if not line:
            continue
        try:
            evt = json.loads(line)
        except json.JSONDecodeError:
            continue
        if isinstance(evt, dict) and evt.get("type") == "error":
            if _looks_like_blocking_usage_message(json.dumps(evt, ensure_ascii=False)):
                return True
    if worker_returncode is not None and worker_returncode != 0:
        return _looks_like_blocking_usage_message(text)
    return False


def parse_reset_datetime(text: str, base: Optional[dt.datetime] = None) -> Optional[dt.datetime]:
    if base is None:
        base = now_local()
    month = (r"(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|"
             r"Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)")
    m = re.search(rf"(?i)resets?.{{0,40}}?{month}\s+(\d{{1,2}})(?:,\s*(\d{{4}}))?"
                  rf".{{0,25}}?(?:at\s+)?(\d{{1,2}})(?::(\d{{2}}))?\s*(am|pm)?", text)
    if m:
        day = int(m.group(2)); year = int(m.group(3)) if m.group(3) else base.year
        hour = int(m.group(4)); minute = int(m.group(5) or 0); ampm = (m.group(6) or "").lower()
        mo = dt.datetime.strptime(m.group(1)[:3].title(), "%b").month
        if ampm == "pm" and hour != 12:
            hour += 12
        elif ampm == "am" and hour == 12:
            hour = 0
        cand = dt.datetime(year, mo, day, hour, minute, tzinfo=base.tzinfo)
        if not m.group(3) and cand < base - dt.timedelta(days=2):
            cand = cand.replace(year=year + 1)
        return cand
    m = re.search(r"(?i)resets?.{0,30}?(?:at\s+)?(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\b", text)
    if m:
        hour = int(m.group(1)); minute = int(m.group(2) or 0); ampm = (m.group(3) or "").lower()
        if ampm == "pm" and hour != 12:
            hour += 12
        elif ampm == "am" and hour == 12:
            hour = 0
        if not (0 <= hour <= 23 and 0 <= minute <= 59):
            return None
        cand = base.replace(hour=hour, minute=minute, second=0, microsecond=0)
        if cand <= base:
            cand += dt.timedelta(days=1)
        return cand
    return None


def wait_for_usage_reset(*, text: str, fallback_minutes: int, safety_seconds: int,
                         stop_event: threading.Event) -> bool:
    parsed = parse_reset_datetime(text)
    if parsed:
        wake = parsed + dt.timedelta(seconds=safety_seconds)
        print(f"Usage limit detected. Reset ~{parsed:%Y-%m-%d %H:%M:%S}; "
              f"waiting until {wake:%H:%M:%S}.", flush=True)
    else:
        wake = now_local() + dt.timedelta(minutes=fallback_minutes)
        print(f"Usage limit detected; reset time not parseable. "
              f"Waiting {fallback_minutes} min.", flush=True)
    while True:
        if stop_event.is_set():
            return False
        remaining = (wake - now_local()).total_seconds()
        if remaining <= 0:
            return True
        print(f"[{now_local():%H:%M:%S}] waiting for reset | "
              f"{dt.timedelta(seconds=int(remaining))} remaining", flush=True)
        stop_event.wait(timeout=min(60.0, max(1.0, remaining)))


# ===========================================================================
# Independent validation  (config-driven)
# ===========================================================================

@dataclass
class ValidationConfig:
    max_file_mb: int = 10
    deny_globs: list[str] = field(default_factory=lambda: [
        "**/.env", "**/.env.*", "**/*.pem", "**/*.p12", "**/*.pfx",
        "**/id_rsa", "**/id_ed25519", "**/credentials", "**/secrets*.json"])
    allow_globs: list[str] = field(default_factory=lambda: [
        "**/.env.example", "**/.env.sample", "**/.env.template"])
    always: list[dict] = field(default_factory=list)
    rules: list[dict] = field(default_factory=list)


def default_validation_config() -> ValidationConfig:
    """A conservative, language-agnostic default when no config is supplied."""
    return ValidationConfig(
        always=[{"label": "whitespace/conflict-marker check",
                 "argv": ["git", "diff", "--check"]}],
        rules=[{"when_touched": ["**/*.py"],
                "commands": [{"label": "python compileall (changed dirs)",
                              "argv": ["__PY__", "-m", "compileall", "-q", "__CHANGED_DIRS__"]}]}],
    )


def load_validation_config(path: Optional[Path]) -> ValidationConfig:
    if path is None:
        return default_validation_config()
    data = load_config_file(path)
    cfg = default_validation_config()
    susp = data.get("suspicious", {}) or {}
    return ValidationConfig(
        max_file_mb=int(susp.get("max_file_mb", cfg.max_file_mb)),
        deny_globs=list(susp.get("deny_globs", cfg.deny_globs)),
        allow_globs=list(susp.get("allow_globs", cfg.allow_globs)),
        always=list(data.get("always", [])),
        rules=list(data.get("rules", [])),
    )


def suspicious_changed_files(repo: Path, files: list[str], cfg: ValidationConfig) -> list[str]:
    bad: list[str] = []
    for rel in files:
        p = Path(rel.replace("\\", "/"))
        rel_norm = rel.replace("\\", "/")
        if any(glob_match(rel_norm, g) for g in cfg.allow_globs):
            pass
        elif any(glob_match(rel_norm, g) for g in cfg.deny_globs):
            bad.append(rel)
            continue
        full = repo / rel
        if full.is_file():
            try:
                if full.stat().st_size > cfg.max_file_mb * 1024 * 1024:
                    bad.append(f"{rel} (> {cfg.max_file_mb} MB)")
            except OSError:
                pass
    return bad


@dataclass
class ValidationResult:
    passed: bool
    details: str


def _touched(files: list[str], globs: list[str]) -> bool:
    norm = [f.replace("\\", "/") for f in files]
    return any(glob_match(f, g) for f in norm for g in globs)


def _changed_dirs(files: list[str]) -> list[str]:
    # Map every changed file to its directory; repo-root files map to ".".
    # Crucially we KEEP "." so a top-level file is always validated, even when
    # other changes live in subdirectories.
    dirs = set()
    for f in files:
        parent = str(Path(f.replace("\\", "/")).parent)
        dirs.add("." if parent in ("", ".") else parent)
    return sorted(dirs) or ["."]


def _expand_argv(argv: list[str], tools: Tools, changed_dirs: list[str]) -> list[list[str]]:
    """Expand placeholder tokens. __CHANGED_DIRS__ fans out to one command."""
    if "__CHANGED_DIRS__" in argv:
        cmds = []
        for d in changed_dirs:
            cmds.append([tools.python if t == "__PY__" else (d if t == "__CHANGED_DIRS__" else t)
                         for t in argv])
        return cmds
    return [[tools.python if t == "__PY__" else t for t in argv]]


def run_validation_command(label: str, argv: Sequence[str], *, cwd: Path) -> tuple[bool, str]:
    print(f"VALIDATE: {label}", flush=True)
    try:
        cp = run_capture(argv, cwd=cwd, check=False)
    except FileNotFoundError:
        # A validation command referenced a tool that isn't on PATH (e.g. `bash`
        # on Windows). Fail the gate cleanly instead of crashing the supervisor.
        exe = str(argv[0]) if argv else "?"
        msg = (f"FAIL: {label} (executable not found: {exe!r})\n"
               f"'{exe}' is not on PATH for this user. Fix the command in your "
               f"--validate config, or install/expose that tool.")
        print(msg, flush=True)
        return False, msg
    out = cp.stdout.rstrip()
    for line in out.splitlines()[-40:]:
        print(line, flush=True)
    ok = cp.returncode == 0
    return ok, f"{'PASS' if ok else 'FAIL'}: {label} (exit {cp.returncode})\n{out}"


def independent_validation(repo: Path, tools: Tools, changed_files: list[str],
                           cfg: ValidationConfig) -> ValidationResult:
    details: list[str] = []
    passed = True
    changed_dirs = _changed_dirs(changed_files)

    for entry in cfg.always:
        for argv in _expand_argv(list(entry["argv"]), tools, changed_dirs):
            argv = [tools.git if a == "git" else a for a in argv]
            ok, text = run_validation_command(entry.get("label", " ".join(argv)), argv, cwd=repo)
            passed &= ok
            details.append(text)

    for rule in cfg.rules:
        if not _touched(changed_files, list(rule.get("when_touched", []))):
            continue
        for cmd in rule.get("commands", []):
            cwd = repo / cmd["cwd"] if cmd.get("cwd") else repo
            if not cwd.exists():
                passed = False
                details.append(f"FAIL: {cmd.get('label','?')} — cwd missing: {cwd}")
                continue
            for argv in _expand_argv(list(cmd["argv"]), tools, changed_dirs):
                argv = [tools.git if a == "git" else a for a in argv]
                ok, text = run_validation_command(cmd.get("label", " ".join(argv)), argv, cwd=cwd)
                passed &= ok
                details.append(text)

    return ValidationResult(passed=passed, details="\n\n".join(details))


# ===========================================================================
# Commit / close / stash
# ===========================================================================

def close_issue_with_retry(repo: Path, tools: Tools, repo_name: str,
                           number: int, comment: str, attempts: int = 3) -> bool:
    for i in range(1, attempts + 1):
        cp = run_capture([tools.gh, "issue", "close", str(number), "--repo",
                          repo_name, "--comment", comment], cwd=repo, check=False)
        if cp.returncode == 0:
            return True
        print(f"issue close attempt {i}/{attempts} failed:\n{cp.stdout}", flush=True)
        if i < attempts:
            time.sleep(5 * i)
    return False


def defer_failed_work(repo: Path, tools: Tools, number: int) -> Optional[str]:
    """Park partial work under tag deferred/issue-<N> so the tree is clean again.

    A tag (not a stash or a branch) keeps one findable entry per issue and never
    clutters the branch list. Restore with `git stash apply deferred/issue-<N>`."""
    if not get_dirty_status(repo, tools.git):
        return None
    msg = f"issue-runner deferred issue #{number} at {now_local():%Y-%m-%d %H:%M:%S}"
    git(repo, tools.git, "stash", "push", "-u", "-m", msg)
    if get_dirty_status(repo, tools.git):
        raise SupervisorError(
            f"git stash left a dirty tree after deferring #{number}. Stopping.")
    tag = f"deferred/issue-{number}"
    git(repo, tools.git, "tag", "-f", tag, "stash@{0}")
    git(repo, tools.git, "stash", "drop", "stash@{0}")
    return tag


CLOSES_RE = re.compile(r"(?im)^\s*(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\s+#(\d+)\b")


def issues_committed_on_branch(repo: Path, git_exe: str, base: str) -> set[int]:
    """Issue numbers referenced by 'Closes #N' in commits not yet on origin/<base>."""
    log = git(repo, git_exe, "log", "--format=%B", f"origin/{base}..HEAD", check=False)
    return {int(n) for n in CLOSES_RE.findall(log)}


# ===========================================================================
# Run creation / resume
# ===========================================================================

def ref_exists(repo: Path, git_exe: str, ref: str) -> bool:
    return run_capture([git_exe, "show-ref", "--verify", "--quiet", ref],
                       cwd=repo, check=False).returncode == 0


def select_work_branch(repo: Path, git_exe: str, base: str,
                       branch: str, mode: str, sync: str = "warn") -> str:
    """Pick the branch to work on and return its name.

    auto: continue the checked-out branch if it is not the base, else behave
    like reuse on `branch`. reuse: continue `branch` (local, then origin), else
    create it from origin/<base>. new: always create a fresh `branch`."""
    git(repo, git_exe, "fetch", "origin")
    base_ref = f"refs/remotes/origin/{base}"
    if not ref_exists(repo, git_exe, base_ref):
        raise SupervisorError(f"Fetched base branch origin/{base} was not found.")
    if mode == "auto":
        current = git(repo, git_exe, "branch", "--show-current").strip()
        if current and current != base:
            print(f"Continuing active branch {current}.", flush=True)
            branch = current
        elif not branch:
            raise SupervisorError("branch_mode=auto needs a work_branch when on the base branch.")
        mode = "reuse"
    if branch == base:
        raise SupervisorError("Work branch must differ from the base branch.")
    local_ref = f"refs/heads/{branch}"
    remote_ref = f"refs/remotes/origin/{branch}"
    if ref_exists(repo, git_exe, local_ref):
        if mode == "new":
            raise SupervisorError(
                f"Branch {branch} already exists. Choose another name or branch_mode=reuse.")
        git(repo, git_exe, "switch", branch)
    elif mode == "reuse" and ref_exists(repo, git_exe, remote_ref):
        git(repo, git_exe, "switch", "--track", "-c", branch, f"origin/{branch}")
    else:
        if ref_exists(repo, git_exe, remote_ref):
            raise SupervisorError(
                f"Branch origin/{branch} already exists. Use branch_mode=reuse or another name.")
        git(repo, git_exe, "switch", "--no-track", "-c", branch, f"origin/{base}")

    if mode == "reuse":
        sync_with_base(repo, git_exe, base, branch, sync)
    return branch


def sync_with_base(repo: Path, git_exe: str, base: str, branch: str, sync: str) -> None:
    """Bring a reused branch up to origin/<base> before new work lands on it."""
    behind = int(git(repo, git_exe, "rev-list", "--count", f"HEAD..origin/{base}").strip())
    if not behind:
        return
    ahead = int(git(repo, git_exe, "rev-list", "--count", f"origin/{base}..HEAD").strip())
    if not ahead:
        # Everything on the branch is already in base (e.g. its PR was merged).
        git(repo, git_exe, "merge", "--ff-only", f"origin/{base}")
        print(f"{branch} was fully merged; fast-forwarded to origin/{base}.", flush=True)
    elif sync == "merge":
        cp = run_capture([git_exe, "merge", "--no-edit", f"origin/{base}"], cwd=repo, check=False)
        if cp.returncode != 0:
            run_capture([git_exe, "merge", "--abort"], cwd=repo, check=False)
            raise SupervisorError(
                f"{branch} conflicts with origin/{base}; the merge was aborted and nothing "
                f"changed. Resolve it by hand (git merge origin/{base}) and rerun.\n{cp.stdout}")
        print(f"Merged {behind} commit(s) from origin/{base} into {branch}.", flush=True)
    elif sync == "stop":
        raise SupervisorError(
            f"{branch} is {behind} commit(s) behind origin/{base}. Merge it first, then rerun.")
    else:
        print(f"NOTICE: {branch} is {behind} base commit(s) behind origin/{base}. "
              "No merge or rebase was performed; inspect before opening a PR.", flush=True)


def prepare_new_run(args, repo: Path, tools: Tools, f: Filters) -> RunState:
    if get_dirty_status(repo, tools.git):
        raise SupervisorError("Working tree is not clean. Commit/stash before a NEW run.\n\n"
                              + get_dirty_status(repo, tools.git))
    repo_name = get_repo_name(repo, tools)
    issues = list_matching_issues(repo, tools, repo_name, f, args.issue_limit)
    if not issues:
        raise SupervisorError("No open issues match the given filters.")

    stamp = now_local().strftime("%Y%m%d-%H%M%S-%f")
    branch = args.work_branch or f"automation/agent-{stamp}"
    branch = select_work_branch(repo, tools.git, args.base_branch, branch,
                                args.branch_mode, args.sync_base)
    done = issues_committed_on_branch(repo, tools.git, args.base_branch)
    issues = [x for x in issues if x.number not in done]
    if not issues:
        raise SupervisorError(f"Every matching issue is already committed on {branch} "
                              "and closes when its PR is merged. Review and merge it.")

    run_dir = runs_root() / stamp
    run_dir.mkdir(parents=True, exist_ok=False)
    state = RunState(
        repo_path=str(repo), repo_name=repo_name, branch=branch, run_dir=str(run_dir),
        frozen_numbers=[x.number for x in issues],
        frozen_titles={str(x.number): x.title for x in issues},
        started_at=iso_now(), base_branch=args.base_branch)
    state.save()
    return state


def resume_run(state_path: Path, tools: Tools) -> tuple[RunState, Path]:
    state = RunState.load(state_path)
    repo = Path(state.repo_path).resolve()
    if not repo.exists():
        raise SupervisorError(f"Resume repo no longer exists: {repo}")
    current = git(repo, tools.git, "branch", "--show-current").strip()
    if current != state.branch:
        if get_dirty_status(repo, tools.git):
            raise SupervisorError(
                f"Cannot resume {state.branch}: dirty tree on {current!r}.")
        git(repo, tools.git, "checkout", state.branch)
    state.status = "running"
    state.save()
    return state, repo


# ===========================================================================
# Supervisor loop
# ===========================================================================

def supervisor(args) -> int:
    tools = discover_core_tools()
    spec, exe = resolve_agent(args.agent)
    # Effective model: --model / config wins, else the agent config's own model.
    effective_model = (args.model or "").strip() or spec.model or None
    vcfg = load_validation_config(Path(args.validate).resolve() if args.validate else None)
    contract = (Path(args.contract_file).read_text(encoding="utf-8")
                if args.contract_file else DEFAULT_CONTRACT)

    if run_capture([tools.gh, "auth", "status"], check=False).returncode != 0:
        raise SupervisorError("GitHub CLI not authenticated. Run `gh auth login`.")

    exclude = {int(x) for x in str(args.exclude).split(",") if x.strip().isdigit()}
    filters = Filters(min_issue=args.min_issue, max_issue=args.max_issue,
                      milestone=args.milestone,
                      labels=[x.strip() for x in args.labels.split(",") if x.strip()],
                      labels_all=args.labels_all, exclude=exclude)

    if args.resume_latest and args.resume_branch:
        raise SupervisorError("Use only one of --resume-latest / --resume-branch.")
    requested_repo = Path(args.repo).expanduser().resolve()
    if args.resume_latest:
        state, repo = resume_run(find_latest_resumable_state(runs_root(), requested_repo), tools)
        print(f"Resuming latest run: {state.run_dir}", flush=True)
    elif args.resume_branch:
        found = find_latest_resumable_state(runs_root(), requested_repo, args.resume_branch)
        state, repo = resume_run(found, tools)
    else:
        repo = Path(args.repo).expanduser().resolve()
        if not repo.exists():
            raise SupervisorError(f"Repo not found: {repo}")
        state = prepare_new_run(args, repo, tools, filters)

    print(f"\nSupervisor ready.\nRepo:    {state.repo_name}\nPath:    {repo}\n"
          f"Branch:  {state.branch}\nAgent:   {spec.name} ({exe})\n"
          f"Model:   {effective_model or '(agent default)'}\n"
          f"Issues:  {', '.join(map(str, state.frozen_numbers))}\n"
          f"Run dir: {state.run_dir}\n"
          f"Closing: {'on PR merge (Closes #N)' if args.close_on == 'merge' else 'on commit'}\n"
          f"Safety:  agent edits; Python validates/commits; never pushes unless asked.\n")

    invocation_start = time.monotonic()
    stop_event = threading.Event()

    def request_stop(signum=None, frame=None):
        if not stop_event.is_set():
            print("\nStop requested; finishing current boundary safely.", flush=True)
            stop_event.set()

    for sig in (signal.SIGINT, signal.SIGTERM):
        try:
            signal.signal(sig, request_stop)
        except (ValueError, OSError):
            pass

    while not stop_event.is_set():
        if args.max_hours > 0 and (time.monotonic() - invocation_start) / 3600 >= args.max_hours:
            print("MaxHours reached; not launching a new worker.", flush=True)
            break
        remaining = get_open_frozen_issues(repo, tools, state.repo_name, state, exclude)
        if not remaining:
            print("All frozen issues are closed.", flush=True)
            break
        eligible = [x for x in remaining if str(x.number) not in state.deferred]
        if not eligible:
            print("All remaining frozen issues are deferred.", flush=True)
            break

        issue = eligible[0]                 # ascending -> lowest open eligible number
        nkey = str(issue.number)
        state.attempt += 1
        state.current_issue = issue.number
        state.save()

        issue_text = get_issue_text(repo, tools, state.repo_name, issue.number)
        dirty_before = get_dirty_status(repo, tools.git)
        prompt = make_prompt(state.repo_name, issue, issue_text, contract, spec,
                             dirty_before, state.previous_failure.get(nkey, ""))

        tag = f"attempt_{state.attempt:03d}_issue_{issue.number}_{now_local():%Y%m%d-%H%M%S}"
        run_dir = Path(state.run_dir)
        log_path = run_dir / f"{tag}.log"
        prompt_path = run_dir / f"{tag}_prompt.txt"
        last_path = run_dir / f"{tag}_last.txt"
        validation_path = run_dir / f"{tag}_validation.txt"

        print(f"\nLaunching {spec.name} for issue #{issue.number}: {issue.title}")
        outcome = run_agent_worker(
            repo=repo, spec=spec, exe=exe, model=effective_model, prompt=prompt,
            log_path=log_path, prompt_path=prompt_path, last_path=last_path,
            stall_minutes=args.stall_minutes, max_session_minutes=args.max_session_minutes,
            heartbeat_seconds=args.heartbeat_seconds, issue=issue, attempt=state.attempt)

        combined = outcome.output_text
        if last_path.exists():
            combined += "\n" + last_path.read_text(encoding="utf-8", errors="replace")

        if detect_usage_limit(combined, worker_returncode=outcome.returncode):
            print(f"Usage/rate limit during #{issue.number}; not consuming a retry.", flush=True)
            state.previous_failure[nkey] = (
                f"Worker ended on a usage/rate limit. Resume from current state. "
                f"Reason: {outcome.reason}")
            state.save()
            if not wait_for_usage_reset(text=combined,
                                        fallback_minutes=args.usage_limit_fallback_minutes,
                                        safety_seconds=args.usage_reset_safety_seconds,
                                        stop_event=stop_event):
                break
            continue

        result = parse_agent_result(combined, spec)
        changed_files = get_changed_files(repo, tools.git)
        dirty_after = get_dirty_status(repo, tools.git)

        print(f"\nWorker ended:  {outcome.reason}\nStatus:        {result.status or '(missing)'}"
              f"\nValidation:    {result.validation or '(missing)'}\nChanged files: {len(changed_files)}")

        candidate = (result.status == "COMPLETE" and result.validation == "PASS"
                     and bool(changed_files))

        if candidate:
            suspicious = suspicious_changed_files(repo, changed_files, vcfg)
            if suspicious:
                candidate = False
                state.previous_failure[nkey] = ("Refused suspicious/large files:\n"
                                                + "\n".join(suspicious))
                print(state.previous_failure[nkey], flush=True)

        if candidate:
            print("\nRunning INDEPENDENT supervisor validation...", flush=True)
            validation = independent_validation(repo, tools, changed_files, vcfg)
            validation_path.write_text(validation.details, encoding="utf-8")
            if not validation.passed:
                candidate = False
                state.previous_failure[nkey] = ("Independent validation failed:\n"
                                                + validation.details[-8000:])
                print("Independent validation FAILED. Nothing committed.", flush=True)
            else:
                print("Independent validation PASSED.", flush=True)

        if candidate:
            git(repo, tools.git, "add", "-A")
            staged = git(repo, tools.git, "diff", "--cached", "--name-only").strip()
            if not staged:
                git(repo, tools.git, "reset")
                candidate = False
                state.previous_failure[nkey] = "No staged changes after git add -A."

        if candidate:
            message = f"issue #{issue.number}: {issue.title}"
            if args.close_on == "merge":
                message += f"\n\nCloses #{issue.number}."
            git(repo, tools.git, "commit", "-m", message)
            commit = git(repo, tools.git, "rev-parse", "--short", "HEAD").strip()
            print(f"Committed issue #{issue.number} as {commit}", flush=True)
            if args.close_on == "commit":
                closed = close_issue_with_retry(
                    repo, tools, state.repo_name, issue.number,
                    f"Implemented on `{state.branch}` in `{commit}`. "
                    f"Independent supervisor validation passed.")
                if not closed:
                    state.deferred[nkey] = f"commit {commit} exists but issue-close failed"
            state.retry_counts.pop(nkey, None)
            state.previous_failure.pop(nkey, None)
            state.current_issue = None
            state.save()
            continue

        # Not committable.
        state.retry_counts[nkey] = state.retry_counts.get(nkey, 0) + 1
        retry = state.retry_counts[nkey]
        state.previous_failure.setdefault(nkey, (
            f"Attempt {retry} produced no validated committable result.\n"
            f"Worker: {outcome.reason}\nStatus: {result.status or '(missing)'}\n"
            f"Validation: {result.validation or '(missing)'}\n"
            f"Worktree:\n{dirty_after}\n\nLog tail:\n{tail_text(log_path, 4000)}"))
        print(f"Issue #{issue.number} not committed. "
              f"Attempt {retry}/{args.max_no_progress_retries}.", flush=True)

        if retry >= args.max_no_progress_retries:
            state.deferred[nkey] = (f"no validated result after "
                                    f"{args.max_no_progress_retries} attempts")
            tag = defer_failed_work(repo, tools, issue.number)
            if tag:
                state.deferred[nkey] += f"; partial work in tag {tag}"
                print(f"Preserved partial work in tag {tag} "
                      f"(restore: git stash apply {tag})", flush=True)
            state.previous_failure.pop(nkey, None)
        state.current_issue = None
        state.save()

    return finalize(state, repo, tools, exclude, args, stop_event)


def finalize(state: RunState, repo: Path, tools: Tools, exclude: set[int],
             args, stop_event: threading.Event) -> int:
    state.current_issue = None
    state.status = "stopped" if stop_event.is_set() else "finished"
    state.save()

    remaining = get_open_frozen_issues(repo, tools, state.repo_name, state, exclude)
    final_dirty = get_dirty_status(repo, tools.git)
    ahead = int(git(repo, tools.git, "rev-list", "--count",
                    f"origin/{state.base_branch}..HEAD").strip())
    done = sorted(issues_committed_on_branch(repo, tools.git, state.base_branch))

    summary = (f"# Issue Supervisor Run\n\n**Repo:** {state.repo_name}\n"
               f"**Branch:** `{state.branch}`\n**Started:** {state.started_at}\n"
               f"**Finished:** {iso_now()}\n**Attempts:** {state.attempt}\n"
               f"**Commits ahead of {state.base_branch}:** {ahead}\n\n"
               "## Committed on this branch (close when the PR merges)\n"
               + ("\n".join(f"Closes #{n}" for n in done) or "- None")
               + "\n\n## Remaining open frozen issues\n"
               + ("\n".join(f"- #{x.number} {x.title}" for x in remaining) or "- None")
               + "\n\n## Deferred issues\n"
               + ("\n".join(f"- #{n}: {r}" for n, r in sorted(state.deferred.items(),
                                                              key=lambda kv: int(kv[0])))
                  or "- None")
               + f"\n\n## Final worktree\n```\n{final_dirty or 'clean'}\n```\n")
    (Path(state.run_dir) / SUMMARY_FILE_NAME).write_text(summary, encoding="utf-8")

    push_status = "not pushed (push disabled)"
    if args.push:
        if final_dirty:
            push_status = "NOT PUSHED: uncommitted changes remain."
            state.status = "interrupted"; state.save()
        elif ahead <= 0:
            push_status = f"NOT PUSHED: no commits ahead of origin/{state.base_branch}."
        else:
            cp = run_capture([tools.git, "push", "-u", "origin", state.branch],
                             cwd=repo, check=False)
            push_status = (f"PUSHED: {ahead} commit(s) on {state.branch}."
                           if cp.returncode == 0 else "PUSH FAILED:\n" + cp.stdout)
            if cp.returncode == 0 and args.open_pr:
                pr = run_capture([tools.gh, "pr", "create", "--repo", state.repo_name,
                                  "--base", state.base_branch, "--head", state.branch,
                                  "--title", f"Automated agent run {state.branch}",
                                  "--body", summary], cwd=repo, check=False)
                push_status += ("\nPR: " + pr.stdout.strip()) if pr.returncode == 0 \
                    else "\nPR creation failed:\n" + pr.stdout

    print("\n================ SUPERVISOR FINISHED ================")
    print(f"Branch:    {state.branch}\nAttempts:  {state.attempt}\n"
          f"Committed: {', '.join(f'#{n}' for n in done) or 'none'}\n"
          f"Remaining: {len(remaining)}\nRun dir:   {state.run_dir}\n{push_status}")
    if ahead > 0 and not args.push:
        base = state.base_branch
        print(f"\nTo review and ship ({ahead} commit(s) ahead of {base}):\n"
              f"  git log --oneline origin/{base}..{state.branch}\n"
              f"  git diff origin/{base}...{state.branch} --stat\n"
              f"  git push -u origin {state.branch}\n"
              f"  gh pr create --base {base} --head {state.branch} --fill\n"
              "Merge with a merge commit (not squash), then rerun: the branch "
              "fast-forwards to the new base automatically.")
    print("====================================================")
    return 0


# ===========================================================================
# Self-test (offline)
# ===========================================================================

def self_test() -> int:
    failures: list[str] = []

    def check(name: str, cond: bool):
        print(f"{'PASS' if cond else 'FAIL'}  {name}")
        if not cond:
            failures.append(name)

    spec = BUILTIN_AGENTS["codex"]
    sample = (f"noise\n{spec.result_begin}\nSTATUS: COMPLETE\nVALIDATION: PASS\n"
              f"ISSUE: #12\nSUMMARY: done\nTESTS: pytest\nNOTES: NONE\n{spec.result_end}\n")
    r = parse_agent_result(sample, spec)
    check("parse status", r.status == "COMPLETE")
    check("parse validation", r.validation == "PASS")

    base = dt.datetime(2026, 9, 8, 17, 0, tzinfo=dt.timezone(dt.timedelta(hours=8)))
    check("clock-only reset", parse_reset_datetime("limit reached - resets 8pm", base)
          == dt.datetime(2026, 9, 8, 20, 0, tzinfo=base.tzinfo))
    check("past clock -> next day", parse_reset_datetime("resets at 3:30 PM", base)
          == dt.datetime(2026, 9, 9, 15, 30, tzinfo=base.tzinfo))
    check("dated reset", parse_reset_datetime("resets Sep 10 at 3pm", base)
          == dt.datetime(2026, 9, 10, 15, 0, tzinfo=base.tzinfo))

    check("blocking usage detected",
          detect_usage_limit('{"type":"error","message":"You have reached your rate limit."}'))
    check("approaching warning ignored", not detect_usage_limit(
        '{"type":"item.completed","item":{"type":"agent_message","text":"approaching your usage limit"}}'))

    # Filter logic
    f = Filters(min_issue=2, max_issue=5, milestone="M1", labels=["a", "b"],
                labels_all=False, exclude={4})
    mk = lambda n, ms, labs: {"number": n, "milestone": {"title": ms},
                              "labels": [{"name": x} for x in labs]}
    check("range low excluded", not _issue_matches(mk(1, "M1", ["a"]), f))
    check("explicit exclude", not _issue_matches(mk(4, "M1", ["a"]), f))
    check("milestone mismatch", not _issue_matches(mk(3, "M2", ["a"]), f))
    check("label any-match", _issue_matches(mk(3, "M1", ["b"]), f))
    check("label none-match", not _issue_matches(mk(3, "M1", ["z"]), f))
    f_all = Filters(2, 5, "", ["a", "b"], True, set())
    check("labels-all requires all", not _issue_matches(mk(3, "", ["a"]), f_all))
    check("labels-all satisfied", _issue_matches(mk(3, "", ["a", "b"]), f_all))

    # Agent argv: model token dropped when no model.
    argv = build_agent_argv(spec, "codex", None, Path("p.txt"), Path("l.txt"), "hi")
    check("model pair removed when no model", "--model" not in argv)
    argv2 = build_agent_argv(spec, "codex", "m1", Path("p.txt"), Path("l.txt"), "hi")
    check("model substituted", "m1" in argv2 and "{MODEL}" not in argv2)
    check("last-msg substituted", "l.txt" in " ".join(argv2))

    # Suspicious-file guard
    cfg = default_validation_config()
    check("deny .env", suspicious_changed_files(Path("."), ["config/.env"], cfg) == ["config/.env"])
    check("allow .env.example", suspicious_changed_files(Path("."), [".env.example"], cfg) == [])

    # Glob matcher (globstar must match top-level and nested)
    check("glob **/*.py top-level", glob_match("app.py", "**/*.py"))
    check("glob **/*.py nested", glob_match("pkg/m.py", "**/*.py"))
    check("glob frontend/** scoped", glob_match("frontend/x.tsx", "frontend/**")
          and not glob_match("backend/x.py", "frontend/**"))
    check("glob **/.env not .env.example",
          glob_match(".env", "**/.env") and not glob_match(".env.example", "**/.env"))
    check("glob **/node_modules/** catches nested packages",
          glob_match("tools/ui/node_modules/a/index.js", "**/node_modules/**")
          and not glob_match("clinic-base/lib/mail.php", "**/mail/**"))
    check("closing keywords parsed",
          CLOSES_RE.findall("x\n\nCloses #4.\nfixes #5\nsee #6") == ["4", "5"])

    # Project-config layering: CLI > config(run section) > hard default.
    _p = build_arg_parser()
    _a = _p.parse_args(["--max-issue", "9"])
    apply_config(_a, {"repo": "/r", "min_issue": 2, "run": {"labels": "x", "push": True}})
    check("config fills repo", _a.repo == "/r")
    check("cli overrides config", _a.max_issue == 9)
    check("run-section label applied", _a.labels == "x")
    check("bool OR-merge from config", _a.push is True)
    check("hard default when unset", _a.stall_minutes == 20)
    check("branch mode defaults to new", _a.branch_mode == "new")
    check("issues close on merge by default", _a.close_on == "merge")

    # Real local Git exercise: multiple logical runs share one work branch.
    git_exe = find_executable("git.exe", "git")
    if git_exe:
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            origin, repo = root / "origin.git", root / "repo"
            run_capture([git_exe, "init", "--bare", "--initial-branch=main", str(origin)])
            run_capture([git_exe, "clone", str(origin), str(repo)])
            git(repo, git_exe, "config", "user.email", "test@example.invalid")
            git(repo, git_exe, "config", "user.name", "Test")
            (repo / "README.md").write_text("base\n", encoding="utf-8")
            git(repo, git_exe, "add", "README.md")
            git(repo, git_exe, "commit", "-m", "base")
            git(repo, git_exe, "push", "origin", "main")
            select_work_branch(repo, git_exe, "main", "automation/repeated", "new")
            check("new work branch does not track main",
                  run_capture([git_exe, "rev-parse", "--abbrev-ref", "@{upstream}"],
                              cwd=repo, check=False).returncode != 0)
            (repo / "README.md").write_text("first run\n", encoding="utf-8")
            git(repo, git_exe, "commit", "-am", "first run")
            first_head = git(repo, git_exe, "rev-parse", "HEAD").strip()
            git(repo, git_exe, "switch", "main")
            select_work_branch(repo, git_exe, "main", "automation/repeated", "reuse")
            check("reuse retains earlier commits",
                  git(repo, git_exe, "rev-parse", "HEAD").strip() == first_head)
            check("reuse does not change main",
                  (repo / "README.md").read_text(encoding="utf-8") == "first run\n"
                  and git(repo, git_exe, "rev-list", "--count", "main..HEAD").strip() == "1")
            try:
                select_work_branch(repo, git_exe, "missing", "automation/other", "new")
            except SupervisorError:
                check("fetch failure leaves current branch intact",
                      git(repo, git_exe, "branch", "--show-current").strip()
                      == "automation/repeated")
            else:
                check("fetch failure leaves current branch intact", False)

            def land_on_main(name: str, text: str) -> None:
                git(repo, git_exe, "switch", "main")
                git(repo, git_exe, "pull", "--ff-only", "origin", "main")
                (repo / name).write_text(text, encoding="utf-8")
                git(repo, git_exe, "add", name)
                git(repo, git_exe, "commit", "-m", f"main: {name}")
                git(repo, git_exe, "push", "origin", "main")
                git(repo, git_exe, "switch", "automation/repeated")

            # auto: an active non-base branch wins over the configured name.
            picked = select_work_branch(repo, git_exe, "main", "automation/configured", "auto")
            check("auto continues the active branch",
                  picked == "automation/repeated"
                  and not ref_exists(repo, git_exe, "refs/heads/automation/configured"))

            # Closes #N marks an issue as done on the branch until it reaches base.
            (repo / "seven.txt").write_text("7\n", encoding="utf-8")
            git(repo, git_exe, "add", "seven.txt")
            git(repo, git_exe, "commit", "-m", "issue #7: seven\n\nCloses #7.")
            check("committed issues detected from Closes #N",
                  issues_committed_on_branch(repo, git_exe, "main") == {7})

            # Deferred work becomes a tag, leaving no stash and a clean tree.
            (repo / "partial.txt").write_text("wip\n", encoding="utf-8")
            tag = defer_failed_work(repo, Tools(git_exe, "", ""), 9)
            check("deferred work stored as a tag",
                  tag == "deferred/issue-9"
                  and ref_exists(repo, git_exe, "refs/tags/deferred/issue-9")
                  and not get_dirty_status(repo, git_exe)
                  and not git(repo, git_exe, "stash", "list").strip())
            git(repo, git_exe, "stash", "apply", "deferred/issue-9")
            check("deferred tag restores the work",
                  (repo / "partial.txt").read_text(encoding="utf-8") == "wip\n")
            (repo / "partial.txt").unlink()

            # A branch behind base gets base merged in when sync=merge.
            land_on_main("other.txt", "main\n")
            select_work_branch(repo, git_exe, "main", "", "auto", "merge")
            check("sync=merge brings base into the branch",
                  (repo / "other.txt").exists()
                  and git(repo, git_exe, "rev-list", "--count", "HEAD..origin/main").strip() == "0")

            # A conflicting base is aborted cleanly and the run stops.
            (repo / "README.md").write_text("branch\n", encoding="utf-8")
            git(repo, git_exe, "commit", "-am", "branch edit")
            before = git(repo, git_exe, "rev-parse", "HEAD").strip()
            land_on_main("README.md", "main edit\n")
            try:
                select_work_branch(repo, git_exe, "main", "", "auto", "merge")
            except SupervisorError:
                check("conflicting sync is aborted and leaves the branch unchanged",
                      git(repo, git_exe, "rev-parse", "HEAD").strip() == before
                      and not get_dirty_status(repo, git_exe)
                      and not (repo / ".git" / "MERGE_HEAD").exists())
            else:
                check("conflicting sync is aborted and leaves the branch unchanged", False)

            # Once the branch is merged into base (merge commit), a rerun fast-forwards it.
            git(repo, git_exe, "merge", "-X", "ours", "--no-edit", "origin/main")
            git(repo, git_exe, "switch", "main")
            git(repo, git_exe, "pull", "--ff-only", "origin", "main")
            git(repo, git_exe, "merge", "--no-ff", "--no-edit", "automation/repeated")
            git(repo, git_exe, "push", "origin", "main")
            git(repo, git_exe, "switch", "automation/repeated")
            select_work_branch(repo, git_exe, "main", "", "auto", "merge")
            check("merged branch fast-forwards to base",
                  git(repo, git_exe, "rev-parse", "HEAD").strip()
                  == git(repo, git_exe, "rev-parse", "origin/main").strip()
                  and issues_committed_on_branch(repo, git_exe, "main") == set())

    # Custom prompt edits from the agent spec are injected around the prompt.
    _spec = AgentSpec(name="t", exe_candidates=["x"], argv=["{EXE}"],
                      prompt_prefix="PXY", prompt_suffix="SXY")
    _pr = make_prompt("me/r", Issue(1, "t"), "body", DEFAULT_CONTRACT, _spec, "", "")
    check("prompt_prefix injected before context",
          "PXY" in _pr and _pr.index("PXY") < _pr.index("CONTEXT READING"))
    check("prompt_suffix injected before result contract",
          "SXY" in _pr and _pr.index("SXY") < _pr.index("FINAL RESPONSE CONTRACT"))

    check("Python >= 3.10", sys.version_info >= (3, 10))

    if failures:
        print("\nSELF-TEST FAILED:", ", ".join(failures))
        return 1
    print("\nALL SELF-TESTS PASSED")
    return 0


# ===========================================================================
# CLI
# ===========================================================================

# Hard defaults for every config-backed option. A value flows:
#   command-line flag  >  project config file  >  this table.
HARD_DEFAULTS: dict[str, object] = {
    "repo": ".", "min_issue": 0, "max_issue": 0, "labels": "", "milestone": "",
    "exclude": "", "issue_limit": 200, "agent": "codex", "model": "",
    "contract_file": "", "validate": "", "base_branch": "main", "work_branch": "",
    "branch_mode": "new", "close_on": "merge", "sync_base": "warn",
    "max_hours": 6.0, "stall_minutes": 20, "max_session_minutes": 45,
    "max_no_progress_retries": 3, "heartbeat_seconds": 60,
    "usage_limit_fallback_minutes": 15, "usage_reset_safety_seconds": 90,
}
# Config keys that are booleans; command-line ON is OR-merged with the config.
BOOL_KEYS = ("labels_all", "push", "open_pr")

DEFAULT_CONFIG_NAMES = ("issue-automation.config.json",
                        "issue-automation.config.yaml",
                        "issue-automation.config.yml")


def build_arg_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(
        description="Supervise a coding agent across GitHub issues, filtered by "
                    "range/labels/milestone, ascending order, one validated commit each. "
                    "Flags override a project config file (see --config).")
    # Config-backed options default to None so we can tell "unset" from "set".
    p.add_argument("--config", default="",
                   help="Project config file (JSON/YAML). If omitted, "
                        "issue-automation.config.* in the current dir (then the repo) is used.")
    p.add_argument("--repo", default=None, help="Repository path.")
    # Deterministic selection
    p.add_argument("--min-issue", type=int, default=None, help="Lowest issue number (inclusive).")
    p.add_argument("--max-issue", type=int, default=None, help="Highest issue number (inclusive).")
    p.add_argument("--labels", default=None, help="Comma-separated labels to match (ANY by default).")
    p.add_argument("--labels-all", action="store_true", help="Require ALL --labels, not any.")
    p.add_argument("--milestone", default=None, help="Only issues in this milestone.")
    p.add_argument("--exclude", default=None, help="Comma-separated issue numbers to skip.")
    p.add_argument("--issue-limit", type=int, default=None, help="Max issues to scan.")
    # Agent
    p.add_argument("--agent", default=None,
                   help="Builtin agent name (codex/claude/aider) or path to an agent config.")
    p.add_argument("--model", default=None, help="Model passed to the agent (blank = agent default).")
    p.add_argument("--contract-file", default=None, help="File with the agent safety contract.")
    # Validation
    p.add_argument("--validate", default=None, help="Validation config file (JSON/YAML).")
    # Branch / output
    p.add_argument("--base-branch", default=None, help="Branch to fork from / compare against.")
    p.add_argument("--work-branch", default=None, help="Automation branch name (default: auto).")
    p.add_argument("--branch-mode", choices=("new", "reuse", "auto"), default=None,
                   help="new: require a fresh branch; reuse: continue an existing named branch; "
                        "auto: continue the checked-out branch unless it is the base, else reuse.")
    p.add_argument("--close-on", choices=("merge", "commit"), default=None,
                   help="merge: commit 'Closes #N' and let the PR merge close it; "
                        "commit: close the issue right after its validated commit.")
    p.add_argument("--sync-base", choices=("merge", "warn", "stop"), default=None,
                   help="When a reused branch is behind base: merge base in (abort on "
                        "conflict), just warn, or stop.")
    p.add_argument("--push", action="store_true", help="Push the automation branch at the end.")
    p.add_argument("--open-pr", action="store_true", help="Open a PR after a successful push.")
    # Watchdog / limits
    p.add_argument("--max-hours", type=float, default=None, help="Wall-clock cap; 0 = unlimited.")
    p.add_argument("--stall-minutes", type=int, default=None)
    p.add_argument("--max-session-minutes", type=int, default=None)
    p.add_argument("--max-no-progress-retries", type=int, default=None)
    p.add_argument("--heartbeat-seconds", type=int, default=None)
    p.add_argument("--usage-limit-fallback-minutes", type=int, default=None)
    p.add_argument("--usage-reset-safety-seconds", type=int, default=None)
    # Resume / test (never config-backed)
    p.add_argument("--resume-latest", action="store_true")
    p.add_argument("--resume-branch", default="")
    p.add_argument("--self-test", action="store_true")
    return p


def load_project_config(explicit: str, repo_hint: str) -> tuple[dict, Optional[Path]]:
    """Return (config dict, path). Explicit --config wins; else auto-discover."""
    if explicit:
        path = Path(explicit).expanduser().resolve()
        if not path.exists():
            raise SupervisorError(f"--config file not found: {path}")
        return load_config_file(path), path
    search_dirs = [Path.cwd()]
    if repo_hint:
        search_dirs.append(Path(repo_hint).expanduser())
    search_dirs.append(Path(__file__).resolve().parent)   # config shipped beside the script
    for d in search_dirs:
        for name in DEFAULT_CONFIG_NAMES:
            cand = (d / name)
            if cand.exists():
                return load_config_file(cand), cand.resolve()
    return {}, None


def apply_config(args, config: dict) -> None:
    """Fill unset args from config, then from HARD_DEFAULTS. CLI always wins.

    A config may be flat, or nest run-only keys under a `run` section; both are
    read. Booleans are OR-merged (CLI --flag or config true)."""
    merged = dict(config)
    merged.update(config.get("run", {}) or {})    # run-section overrides flat for the runner
    for key, hard in HARD_DEFAULTS.items():
        if getattr(args, key) is None:
            setattr(args, key, merged.get(key, hard))
    for key in BOOL_KEYS:
        setattr(args, key, bool(getattr(args, key)) or bool(merged.get(key, False)))


def validate_args(args) -> None:
    resuming = args.resume_latest or args.resume_branch
    if args.branch_mode not in {"new", "reuse", "auto"}:
        raise SupervisorError("branch_mode must be 'new', 'reuse' or 'auto'.")
    if args.close_on not in {"merge", "commit"}:
        raise SupervisorError("close_on must be 'merge' or 'commit'.")
    if args.sync_base not in {"merge", "warn", "stop"}:
        raise SupervisorError("sync_base must be 'merge', 'warn' or 'stop'.")
    if not resuming and args.branch_mode == "reuse" and not args.work_branch:
        raise SupervisorError("branch_mode=reuse requires a stable work_branch.")
    if not args.base_branch:
        raise SupervisorError("base_branch must name a branch on origin.")
    if not args.self_test and not resuming:
        if args.min_issue and args.max_issue and args.max_issue < args.min_issue:
            raise SupervisorError("--max-issue must be >= --min-issue.")
    if args.max_hours < 0:
        raise SupervisorError("--max-hours must be 0 or greater.")
    if args.stall_minutes < 1:
        raise SupervisorError("--stall-minutes must be at least 1.")
    if args.max_session_minutes < 5:
        raise SupervisorError("--max-session-minutes must be at least 5.")
    if args.max_no_progress_retries < 1:
        raise SupervisorError("--max-no-progress-retries must be at least 1.")
    if args.heartbeat_seconds < 5:
        raise SupervisorError("--heartbeat-seconds must be at least 5.")


def main() -> int:
    args = build_arg_parser().parse_args()
    try:
        if args.self_test:
            return self_test()
        config, config_path = load_project_config(args.config, args.repo or ".")
        cli_repo = args.repo
        apply_config(args, config)
        if config_path:
            print(f"Using project config: {config_path}", flush=True)
            # A relative sidecar path in the config resolves next to the config
            # file, so the workflow works from any launch directory.
            base = config_path.parent
            config_repo = str(config.get("repo", "") or "")
            if (cli_repo is None and config_repo
                    and not Path(config_repo).is_absolute()):
                args.repo = str((base / config_repo).resolve())
            for key in ("agent", "validate", "contract_file"):
                val = getattr(args, key)
                if val and not Path(val).is_absolute() and val not in BUILTIN_AGENTS:
                    beside = base / val
                    if beside.exists():
                        setattr(args, key, str(beside.resolve()))
        validate_args(args)
        return supervisor(args)
    except KeyboardInterrupt:
        print("\nInterrupted.", flush=True)
        return 130
    except SupervisorError as exc:
        print_err(str(exc))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
