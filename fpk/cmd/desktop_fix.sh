#!/bin/bash
# Best-effort: make Feiniu register a desktop shortcut.
# Leftover docker-project installs can keep is_docker / no_display set, which hides the icon.

ensure_ui_symlink() {
  local appname="${TRIM_APPNAME:-lemon-music}"
  local ui_dir="${TRIM_APPDEST}/ui"
  [ -d "${ui_dir}" ] || return 0
  mkdir -p /var/apps_ui 2>/dev/null || true
  ln -sfn "${ui_dir}" "/var/apps_ui/${appname}" 2>/dev/null || true
}

fix_desktop_db() {
  local appname="${TRIM_APPNAME:-lemon-music}"
  command -v psql >/dev/null 2>&1 || return 0

  run_sql() {
    sudo -u postgres psql -d appcenter -v ON_ERROR_STOP=1 -c "$1" >/dev/null 2>&1 \
      || psql -U postgres -d appcenter -v ON_ERROR_STOP=1 -c "$1" >/dev/null 2>&1
  }

  run_sql "UPDATE app SET is_docker = false, micro_app = false WHERE app_name = '${appname}';" || true
  # 只修正入口可见性/打开方式，不要改 is_admin：
  # 飞牛「谁可以访问」对应 app_service.is_admin（true=仅管理员）。
  # 以前每次启用都写 is_admin=true，会覆盖用户在设置里选的「设备内所有用户」。
  run_sql "UPDATE app_service SET no_display = false, type = 'url' WHERE app_id IN (SELECT id FROM app WHERE app_name = '${appname}');" || true
}

ensure_desktop_entry() {
  ensure_ui_symlink
  fix_desktop_db
}
