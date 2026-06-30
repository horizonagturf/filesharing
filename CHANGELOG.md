# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- Filament admin sidebar **Home** link back to the main application
- Filament dashboard stats widget (users, pending approval, published and active bundles, total downloads)
- CSP-safe initials avatar provider for Filament user menu avatars
- Public `/help` section with topic pages and navigation links

### Fixed

- Invitation OTP forms returning 403 by signing POST URLs for the OTP request and verify routes instead of reusing the invitation show link signature
