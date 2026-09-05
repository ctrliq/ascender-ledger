# Ascender Ledger

[![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](./LICENSE.md)
[![Ascender](https://img.shields.io/badge/ascender-25.5.1-blue.svg)](https://github.com/ctrliq/ascender)

A reporting tool for [Ascender](https://github.com/ctrliq/ascender). Ledger accepts the log stream from one or more Ascender servers, stores the host facts and the changes each playbook run made, and lets you report across both. Recording only changes strips out the noise of unchanged tasks, so what remains is what your automation actually did.

## Requirements

- A running Ascender server configured to send its log stream to Ledger
- Docker with Compose, for a standalone deployment
- A Kubernetes cluster, if deploying through the installer

## Installation

Ledger is normally deployed alongside Ascender by the [Ascender installer](https://github.com/ctrliq/ascender-install), by setting `LEDGER_INSTALL: true`. To run it standalone:

```bash
docker compose up -d
```

## Using Ledger

Once a server is trusted, Ledger records two kinds of data as jobs run, both searchable from the web interface.

### Facts

Fact data is collected from any module that writes to `ansible_facts`. That covers the `setup` module used by `gather_facts`, modules that register facts automatically such as several of the Windows modules, and `set_fact`.

`set_fact` is deliberately allowed, so you can build custom facts in a playbook and report on them. A whitelist for restricting which modules contribute facts is planned.

### Changes

Only changes are recorded. If a job template has Show Changes enabled and the module supports it, such as `lineinfile`, Ledger stores a diff of exactly what changed.

The search box searches the change data itself, so you can ask which automated changes touched `/etc/sudoers`.

### Reports

Both kinds of data can be saved as a report, which is shared with other users and mailed on a schedule.

A facts report picks the facts to show as its columns and selects hosts by comparing their fact values. A changes
report lists the changes the way the Changes page does, narrowed by the same filters: host, job template, playbook,
role, module, job type, inventory, project and a search of the change data. It also takes a window in hours, so a
scheduled report covers the changes of the last night rather than everything ever recorded.

## Authentication

Ledger ignores data from servers it has not been told to trust, so a new server sends nothing useful until you approve it.

- A server appears under Admins then Servers the first time it sends data
- Click the lock icon on that entry to trust it, after which facts and changes populate live
- Edit the entry to set the server's Ascender URL, linking changes back to their job

## Configuration

Container configuration lives in [`files/`](./files), and credentials are passed as Docker secrets rather than environment values. `secrets/admin_password.txt` seeds the initial admin account, and the MySQL password is read from `/run/secrets/db-ledger-password`.

## Included content

Ledger runs as three containers, published under `ghcr.io/ctrliq/ascender-ledger`:

- **`ledger-web`**: the reporting interface, served by nginx and PHP-FPM
- **`ledger-parser`**: receives and parses the incoming log stream from Ascender
- **`ledger-db`**: the MySQL database holding facts and changes

## The Ascender ecosystem

| Repository | Description |
| ---------- | ----------- |
| [ascender](https://github.com/ctrliq/ascender) | The platform itself: web UI, REST API, and task engine |
| [ascender-install](https://github.com/ctrliq/ascender-install) | Installer for Ascender and Ledger, with Galaxy Proxy support |
| [ascender-k8s-install](https://github.com/ctrliq/ascender-k8s-install) | Kubernetes installer for Ascender, Ledger, and React |
| [ascender-pro-install](https://github.com/ctrliq/ascender-pro-install) | Enhanced installer adding Reaqt, Registry, and Galaxy Proxy |
| [ascender-operator](https://github.com/ctrliq/ascender-operator) | Kubernetes operator that deploys and manages Ascender |
| [ascender-ee](https://github.com/ctrliq/ascender-ee) | Default execution environment image for Ascender jobs |
| [ascender-kit](https://github.com/ctrliq/ascender-kit) | The `ascender` command line client and Python API library |
| [ascender-collection](https://github.com/ctrliq/ascender-collection) | The `ctrliq.ascender` Ansible collection for a controller |
| [ascender-ledger](https://github.com/ctrliq/ascender-ledger) | Reporting tool for host facts and playbook changes |
| [ascender-galaxy-proxy](https://github.com/ctrliq/ascender-galaxy-proxy) | Caching proxy for Ansible Galaxy collection downloads |
| [ascender-playbooks](https://github.com/ctrliq/ascender-playbooks) | Example playbooks for use with Ascender |
## Contributing

- See [CONTRIBUTING.md](./CONTRIBUTING.md) for development setup, testing, and pull requests.
- Report bugs and feature ideas via [GitHub Issues](https://github.com/ctrliq/ascender-ledger/issues).
- For security vulnerabilities, follow [SECURITY.md](./SECURITY.md) rather than opening an issue.
- Join the [Ascender forum](https://forum.ascender-automation.org) to discuss development topics.

## License

Licensed under the **Apache License 2.0**. See [LICENSE.md](./LICENSE.md) and [COPYRIGHT.md](./COPYRIGHT.md).
