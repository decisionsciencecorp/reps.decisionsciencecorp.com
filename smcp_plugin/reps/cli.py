#!/usr/bin/env python3
"""
Reps SMCP plugin — CLI wrapping reps_sdk.RepsClient.

Env: REPS_SMCP_API_KEY / REPS_API_KEY / REPS_DSC_AGENT_API_KEY
     REPS_API_BASE_URL (default https://reps.decisionsciencecorp.com)
"""

from __future__ import annotations

import argparse
import json
import os
import sys
import traceback
from pathlib import Path
from typing import Any, Callable, Dict, List, Optional

_REPO = Path(__file__).resolve().parents[2]
_SDK = _REPO / "reps_sdk"
if str(_SDK) not in sys.path:
    sys.path.insert(0, str(_SDK))

from reps_sdk import (  # noqa: E402
    APIError,
    AuthenticationError,
    ForbiddenError,
    NotFoundError,
    RepsClient,
    ValidationError,
)

try:
    from smcp_plugin.reps import __version__ as PLUGIN_VERSION
except ImportError:  # pragma: no cover
    PLUGIN_VERSION = "0.1.0"

PLUGIN = {
    "name": "reps",
    "version": PLUGIN_VERSION,
    "description": (
        "Reps dashboard API — shops, operators, sessions, money pulse, and Partner proxy. "
        "API-key auth only (prefer agent seat). Live Partner writes are real ops."
    ),
}

BASE_URL = os.environ.get(
    "REPS_API_BASE_URL",
    os.environ.get("REPS_DASH_BASE_URL", "https://reps.decisionsciencecorp.com"),
).rstrip("/")

COMMAND_HELP: Dict[str, str] = {
    "health": "Liveness of the Reps dashboard API.",
    "me": "Principal for this API key (role, scope, auth mode).",
    "list-shops": "List shops in scope for this key.",
    "get-shop": "Fetch one shop by --id.",
    "list-operators": "List operators / workers in scope.",
    "get-operator": "Fetch one operator by --id.",
    "list-sessions": "List capture sessions in scope.",
    "get-session": "Fetch one session by --id.",
    "money-summary": "Role-aware money pulse (+ ledger for staff/agent).",
    "list-api-keys": "List API key previews (admin or self).",
    "partner-sync": "POST Partner poll + ingest into the Reps book.",
    "partner-hours-feed": "GET Partner hours feed (optional --ingest).",
    "partner-workers": "GET Partner workers roster.",
    "partner-team-members": "GET Partner team members.",
    "partner-derived-issues": "GET derived issues from local book.",
    "partner-derived-worker": "GET derived worker stats by --id.",
    "partner-derived-day": "GET derived day rollup (--date YYYY-MM-DD).",
    "tool-help": "Short routing cheat sheet for this plugin.",
}


def resolve_api_key(explicit: Optional[str]) -> str:
    if explicit and str(explicit).strip():
        return str(explicit).strip()
    for var in ("REPS_SMCP_API_KEY", "REPS_API_KEY", "REPS_DSC_AGENT_API_KEY"):
        val = os.environ.get(var, "").strip()
        if val:
            return val
    return ""


def _error_response(error: str, error_type: str) -> Dict[str, Any]:
    return {"status": "error", "error": error, "error_type": error_type}


def _success(**data: Any) -> Dict[str, Any]:
    out: Dict[str, Any] = {"status": "success"}
    out.update(data)
    return out


def _wrap(fn: Callable[[], Dict[str, Any]]) -> Dict[str, Any]:
    try:
        return fn()
    except NotFoundError as e:
        return _error_response(str(e), "not_found")
    except ValidationError as e:
        return _error_response(str(e), "validation_error")
    except AuthenticationError as e:
        return _error_response(str(e), "authentication_error")
    except ForbiddenError as e:
        return _error_response(str(e), "forbidden")
    except APIError as e:
        return _error_response(str(e), "api_error")
    except Exception as e:
        return _error_response(str(e), "unexpected_error")


def _arg_type_name(action: argparse.Action) -> str:
    if isinstance(action, argparse._StoreTrueAction):
        return "boolean"
    if action.type is int:
        return "integer"
    if action.type is float:
        return "number"
    return "string"


def _canonical_option_name(action: argparse.Action) -> str:
    for option in action.option_strings:
        if option.startswith("--") and "_" not in option:
            return option[2:]
    for option in action.option_strings:
        if option.startswith("--"):
            return option[2:].replace("_", "-")
    return action.dest.replace("_", "-")


def _describe_action(action: argparse.Action) -> Optional[Dict[str, Any]]:
    if action.dest in {"help", "api_key", "base_url", "describe", "command"}:
        return None
    if action.help == argparse.SUPPRESS:
        return None
    description = action.help or ""
    default_value = None if action.default is argparse.SUPPRESS else action.default
    required = bool(getattr(action, "required", False))
    return {
        "name": _canonical_option_name(action),
        "type": _arg_type_name(action),
        "description": description,
        "required": required,
        "default": default_value,
    }


def build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(description=PLUGIN["description"])
    p.add_argument("--api-key", default="", help="Reps API key (or env)")
    p.add_argument("--base-url", default=BASE_URL, help="Reps site origin")
    p.add_argument("--describe", action="store_true", help="Emit SMCP tool schema JSON")
    sub = p.add_subparsers(dest="command")

    sub.add_parser("health", help=COMMAND_HELP["health"])
    sub.add_parser("me", help=COMMAND_HELP["me"])
    sub.add_parser("list-shops", help=COMMAND_HELP["list-shops"])
    g = sub.add_parser("get-shop", help=COMMAND_HELP["get-shop"])
    g.add_argument("--id", type=int, required=True, help="Shop id")
    sub.add_parser("list-operators", help=COMMAND_HELP["list-operators"])
    g = sub.add_parser("get-operator", help=COMMAND_HELP["get-operator"])
    g.add_argument("--id", type=int, required=True, help="Operator id")
    sub.add_parser("list-sessions", help=COMMAND_HELP["list-sessions"])
    g = sub.add_parser("get-session", help=COMMAND_HELP["get-session"])
    g.add_argument("--id", type=int, required=True, help="Session id")
    sub.add_parser("money-summary", help=COMMAND_HELP["money-summary"])
    g = sub.add_parser("list-api-keys", help=COMMAND_HELP["list-api-keys"])
    g.add_argument("--user-id", type=int, default=0, help="Target user id (admin)")
    sub.add_parser("partner-sync", help=COMMAND_HELP["partner-sync"])
    g = sub.add_parser("partner-hours-feed", help=COMMAND_HELP["partner-hours-feed"])
    g.add_argument("--ingest", action="store_true", help="Also ingest into local book")
    sub.add_parser("partner-workers", help=COMMAND_HELP["partner-workers"])
    sub.add_parser("partner-team-members", help=COMMAND_HELP["partner-team-members"])
    sub.add_parser("partner-derived-issues", help=COMMAND_HELP["partner-derived-issues"])
    g = sub.add_parser("partner-derived-worker", help=COMMAND_HELP["partner-derived-worker"])
    g.add_argument("--id", type=int, required=True, help="Operator id")
    g = sub.add_parser("partner-derived-day", help=COMMAND_HELP["partner-derived-day"])
    g.add_argument("--date", required=True, help="YYYY-MM-DD")
    sub.add_parser("tool-help", help=COMMAND_HELP["tool-help"])
    return p


def describe_tools(parser: argparse.ArgumentParser) -> Dict[str, Any]:
    sub = None
    for action in parser._actions:
        if isinstance(action, argparse._SubParsersAction):
            sub = action
            break
    tools: List[Dict[str, Any]] = []
    if sub:
        for name, sp in sorted(sub.choices.items()):
            params = []
            for a in sp._actions:
                d = _describe_action(a)
                if d:
                    params.append(d)
            tools.append(
                {
                    "name": f"reps__{name.replace('-', '_')}",
                    "description": COMMAND_HELP.get(name, sp.description or name),
                    "parameters": params,
                }
            )
    return {
        "plugin": PLUGIN,
        "tools": tools,
        "auth": {
            "env": ["REPS_SMCP_API_KEY", "REPS_API_KEY", "REPS_DSC_AGENT_API_KEY"],
            "header": "X-API-Key",
        },
    }


def run_command(args: argparse.Namespace) -> Dict[str, Any]:
    cmd = args.command
    if cmd == "tool-help":
        return _success(commands=COMMAND_HELP)

    key = resolve_api_key(args.api_key)
    if not key and cmd != "health":
        return _error_response("API key required", "authentication_error")

    client = RepsClient(api_key=key or "unused", base_url=args.base_url)

    def go() -> Dict[str, Any]:
        if cmd == "health":
            return _success(result=client.health())
        if cmd == "me":
            return _success(result=client.me())
        if cmd == "list-shops":
            return _success(result=client.list_shops())
        if cmd == "get-shop":
            return _success(result=client.get_shop(args.id))
        if cmd == "list-operators":
            return _success(result=client.list_operators())
        if cmd == "get-operator":
            return _success(result=client.get_operator(args.id))
        if cmd == "list-sessions":
            return _success(result=client.list_sessions())
        if cmd == "get-session":
            return _success(result=client.get_session(args.id))
        if cmd == "money-summary":
            return _success(result=client.money_summary())
        if cmd == "list-api-keys":
            uid = args.user_id if args.user_id else None
            return _success(result=client.list_api_keys(user_id=uid))
        if cmd == "partner-sync":
            return _success(result=client.partner_sync())
        if cmd == "partner-hours-feed":
            return _success(result=client.partner_hours_feed(ingest=bool(args.ingest)))
        if cmd == "partner-workers":
            return _success(result=client.partner_workers())
        if cmd == "partner-team-members":
            return _success(result=client.partner_team_members())
        if cmd == "partner-derived-issues":
            return _success(result=client.partner_derived_issues())
        if cmd == "partner-derived-worker":
            return _success(result=client.partner_derived_worker(args.id))
        if cmd == "partner-derived-day":
            return _success(result=client.partner_derived_day(args.date))
        return _error_response(f"Unknown command: {cmd}", "validation_error")

    return _wrap(go)


def main(argv: Optional[List[str]] = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)
    if args.describe:
        print(json.dumps(describe_tools(parser), indent=2))
        return 0
    if not args.command:
        print(json.dumps(describe_tools(parser), indent=2))
        return 0
    out = run_command(args)
    print(json.dumps(out, indent=2, default=str))
    return 0 if out.get("status") == "success" else 1


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except BrokenPipeError:  # pragma: no cover
        raise SystemExit(0)
