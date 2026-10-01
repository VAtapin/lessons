#!/usr/bin/env bash

# The caller holds the operations lock and initializes these state flags.
deployment_exit() {
    original_status=$?
    trap - EXIT
    if $deployment_started && ! $deployment_completed && ! $initially_down && ! $schema_changes_started; then
        if php artisan lessons:check; then
            php artisan up || echo "Maintenance cleanup failed; inspect the application privately." >&2
        else
            echo "Application check failed; maintenance remains enabled." >&2
        fi
    elif $deployment_started && ! $deployment_completed; then
        echo "Maintenance remains enabled; inspect migration/schema state before recovery." >&2
    fi
    exit "$original_status"
}
trap deployment_exit EXIT
