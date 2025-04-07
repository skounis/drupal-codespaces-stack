export const BASE_URL = `${window.location.protocol}//${window.location.host}${drupalSettings.path.baseUrl + drupalSettings.path.pathPrefix}`;
export const FULL_MODULE_PATH = `${BASE_URL}${drupalSettings.project_browser.module_path}`;
export const DARK_COLOR_SCHEME =
  matchMedia('(forced-colors: active)').matches &&
  matchMedia('(prefers-color-scheme: dark)').matches;
export const PACKAGE_MANAGER = drupalSettings.project_browser.package_manager;
export const MAX_SELECTIONS = drupalSettings.project_browser.max_selections;
export const CURRENT_PATH = drupalSettings.project_browser.current_path;
