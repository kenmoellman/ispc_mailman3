#!/usr/bin/env python3
"""
Postorius (Django) user provisioning script.
Called by the ISPConfig3 Mailman3 module to create/update user accounts
so list owners can log into Postorius.

Usage:
    manage_postorius_user.py --email user@domain.com --password 'pass' --action create
    manage_postorius_user.py --email user@domain.com --password 'pass' --action update
    manage_postorius_user.py --email user@domain.com --action delete
"""

import argparse
import os
import sys

# Django setup
os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'settings')
sys.path.insert(0, '/usr/share/mailman3-web')

import django
django.setup()

from django.contrib.auth.models import User


def create_user(email, password):
    """Create a Django user or update password if exists."""
    try:
        user = User.objects.get(email=email)
        user.set_password(password)
        user.save()
        print(f"Updated password for existing user: {email}")
    except User.DoesNotExist:
        # Use email as username (truncated to 150 chars for Django's limit)
        username = email[:150]
        user = User.objects.create_user(
            username=username,
            email=email,
            password=password,
        )
        print(f"Created user: {email}")
    return 0


def update_password(email, password):
    """Update password for existing user."""
    try:
        user = User.objects.get(email=email)
        user.set_password(password)
        user.save()
        print(f"Updated password for: {email}")
        return 0
    except User.DoesNotExist:
        print(f"User not found: {email}", file=sys.stderr)
        return 1


def delete_user(email):
    """Delete a Django user."""
    try:
        user = User.objects.get(email=email)
        user.delete()
        print(f"Deleted user: {email}")
        return 0
    except User.DoesNotExist:
        print(f"User not found: {email}", file=sys.stderr)
        return 1


def main():
    parser = argparse.ArgumentParser(description='Manage Postorius user accounts')
    parser.add_argument('--email', required=True, help='User email address')
    parser.add_argument('--password', help='User password')
    parser.add_argument('--action', required=True, choices=['create', 'update', 'delete'],
                        help='Action to perform')
    args = parser.parse_args()

    if args.action == 'create':
        if not args.password:
            print("Password required for create action", file=sys.stderr)
            sys.exit(1)
        sys.exit(create_user(args.email, args.password))
    elif args.action == 'update':
        if not args.password:
            print("Password required for update action", file=sys.stderr)
            sys.exit(1)
        sys.exit(update_password(args.email, args.password))
    elif args.action == 'delete':
        sys.exit(delete_user(args.email))


if __name__ == '__main__':
    main()
