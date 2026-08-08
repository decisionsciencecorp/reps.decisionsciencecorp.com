"""Python client for the Reps dashboard API."""

from reps_sdk.client import RepsClient
from reps_sdk.exceptions import (
    APIError,
    AuthenticationError,
    ForbiddenError,
    NotFoundError,
    RepsError,
    ValidationError,
)

__all__ = [
    "RepsClient",
    "RepsError",
    "APIError",
    "AuthenticationError",
    "ForbiddenError",
    "NotFoundError",
    "ValidationError",
]
__version__ = "0.1.0"
