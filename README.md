# ProjetVet Moodle Activity Plugin

[![Moodle Plugin CI](https://github.com/call-learning/moodle-mod_projetvet/actions/workflows/gha.yml/badge.svg)](https://github.com/call-learning/moodle-mod_projetvet/actions/workflows/gha.yml)

`mod_projetvet` is a custom Moodle activity module used to manage ProjetVet workflows.

## Compatibility

- Component: `mod_projetvet`
- Moodle required: `2024100700` (Moodle 4.5+)
- Supported branches: Moodle `4.5` to `5.1`

## Installation

1. Copy this plugin to `mod/projetvet` in your Moodle codebase.
2. Visit Site administration to trigger the plugin installation/upgrade.

## Development

- PHP source code is in `classes/`, entry points in the module root.
- Frontend AMD modules are in `amd/src/` with built assets in `amd/build/`.
- Automated tests are in `tests/` (PHPUnit and Behat).
