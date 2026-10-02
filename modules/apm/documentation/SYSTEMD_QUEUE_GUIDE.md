# Systemd queue guide (removed)

CBP host workers now use **Supervisor**. See [SUPERVISOR_QUEUE_GUIDE.md](./SUPERVISOR_QUEUE_GUIDE.md) and `docs/SETUP.md`.

Legacy systemd unit files and installers were deleted from the repository. `scripts/setup/install-supervisor.sh` retires old CBP systemd units when Supervisor is installed.
