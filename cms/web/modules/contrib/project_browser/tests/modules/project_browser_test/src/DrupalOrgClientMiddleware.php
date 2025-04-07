<?php

declare(strict_types=1);

namespace Drupal\project_browser_test;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\project_browser\Plugin\ProjectBrowserSource\DrupalDotOrgJsonApi;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

// cspell:ignore Bcore Bdevelopment Bfulltext Blimit Bmachine Bmaintenance
// cspell:ignore Bmodule Boffset Boperator Bpath Bproject Bsecurity Bstatus
// cspell:ignore Btaxonomy Btype Bvalue Cfield

/**
 * Middleware to intercept Drupal.org API requests during tests.
 */
final class DrupalOrgClientMiddleware {

  /**
   * Json:API Endpoints to fixture mapping.
   *
   * These are the files used and what they contain:
   * - categories.json: List of available categories. Used in all tests.
   * - default_modules.json: List of modules while visiting
   * 'admin/modules/browse' first time. Used in all tests.
   * - items_per_page.json: Items per page changed to 24 on default page.
   * - clear-filters.json: 'Clear filters' clicked.
   * - pager0.json: First 'Next page' clicked.
   * - pager1.json: Second 'Next page' clicked.
   * - pager2.json: 'Accessibility' checked.
   * - pager3.json: 'Accessibility', 'E-commerce' checked.
   * - pager4.json: 'Accessibility', 'E-commerce', 'Media' checked.
   * - sort.json: Sort by created.
   * - filters0.json: 'Security' filter removed.
   * - filters1.json: 'Development' status active.
   * - filters2.json: 'Developer tools', 'E-commerce' are checked and 'th' is
   *   searched.
   * - filters3.json: 'E-commerce' is checked.
   * - filters4.json: 'Developer tools', 'E-commerce' are checked.
   * - filters5.json: 'E-commerce' is checked and 'Security' checked.
   * - filters6.json: 'Media' checked.
   * - filters7.json: 'Developer tools', 'Media' checked.
   *
   * If you need to change the meaning of the queries, see also the script
   * at `scripts/regenerate-drupalorg-jsonapi-fixture.php` where we try to
   * automate fixture regeneration.
   *
   * @const array
   */
  const DRUPALORG_JSONAPI_ENDPOINT_TO_FIXTURE_MAP = [
    '/taxonomy_term/module_categories?sort=name&filter%5Bstatus%5D=1&fields%5Btaxonomy_term--module_categories%5D=name' => 'categories.json',
    '/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B0%5D=089406ad-304d-4737-80d1-2f08527ae49e&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B1%5D=cee844e2-68b5-489d-bafa-6a0ade2b6dfd&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B2%5D=09a378d2-fd35-41f3-bff0-10d9801741a4&filter%5Bmaintenance_status_uuid%5D%5Boperator%5D=IN&filter%5Bmaintenance_status_uuid%5D%5Bpath%5D=maintenance_status_uuid&filter%5Bsecurity_coverage%5D%5Bvalue%5D%5B0%5D=covered&filter%5Bsecurity_coverage%5D%5Boperator%5D=IN&filter%5Bsecurity_coverage%5D%5Bpath%5D=security_coverage&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage' => 'default_modules.json',
    '/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage' => 'clear-filters.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=12&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'pager0.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=24&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'pager1.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B0%5D=3df293b3-c9a1-4232-962b-3c8169e8e6e3&filter%5Bmodule_categories_uuid%5D%5Boperator%5D=IN&filter%5Bmodule_categories_uuid%5D%5Bpath%5D=module_categories_uuid&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'pager2.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B0%5D=3df293b3-c9a1-4232-962b-3c8169e8e6e3&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B1%5D=0cd80c8e-5c20-43a8-aa3e-ec701007d443&filter%5Bmodule_categories_uuid%5D%5Boperator%5D=IN&filter%5Bmodule_categories_uuid%5D%5Bpath%5D=module_categories_uuid&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'pager3.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B0%5D=3df293b3-c9a1-4232-962b-3c8169e8e6e3&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B1%5D=0cd80c8e-5c20-43a8-aa3e-ec701007d443&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B2%5D=68428c33-1db7-438d-b1b3-e23004e0982b&filter%5Bmodule_categories_uuid%5D%5Boperator%5D=IN&filter%5Bmodule_categories_uuid%5D%5Bpath%5D=module_categories_uuid&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'pager4.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&sort=-created&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'sort.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B0%5D=089406ad-304d-4737-80d1-2f08527ae49e&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B1%5D=cee844e2-68b5-489d-bafa-6a0ade2b6dfd&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B2%5D=09a378d2-fd35-41f3-bff0-10d9801741a4&filter%5Bmaintenance_status_uuid%5D%5Boperator%5D=IN&filter%5Bmaintenance_status_uuid%5D%5Bpath%5D=maintenance_status_uuid&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'filters0.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B0%5D=089406ad-304d-4737-80d1-2f08527ae49e&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B1%5D=cee844e2-68b5-489d-bafa-6a0ade2b6dfd&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B2%5D=09a378d2-fd35-41f3-bff0-10d9801741a4&filter%5Bmaintenance_status_uuid%5D%5Boperator%5D=IN&filter%5Bmaintenance_status_uuid%5D%5Bpath%5D=maintenance_status_uuid&filter%5Bdevelopment_status_uuid%5D%5Bvalue%5D%5B0%5D=e767288c-9800-4fb4-aeb8-8c311533838a&filter%5Bdevelopment_status_uuid%5D%5Bvalue%5D%5B1%5D=219c1cf2-dd7f-474b-9dd5-a26643fbc699&filter%5Bdevelopment_status_uuid%5D%5Boperator%5D=IN&filter%5Bdevelopment_status_uuid%5D%5Bpath%5D=development_status_uuid&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'filters1.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bfulltext%5D=th&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B0%5D=086cebcf-200f-4c34-886e-f9921919b292&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B1%5D=0cd80c8e-5c20-43a8-aa3e-ec701007d443&filter%5Bmodule_categories_uuid%5D%5Boperator%5D=IN&filter%5Bmodule_categories_uuid%5D%5Bpath%5D=module_categories_uuid&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'filters2.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B0%5D=0cd80c8e-5c20-43a8-aa3e-ec701007d443&filter%5Bmodule_categories_uuid%5D%5Boperator%5D=IN&filter%5Bmodule_categories_uuid%5D%5Bpath%5D=module_categories_uuid&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'filters3.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B0%5D=086cebcf-200f-4c34-886e-f9921919b292&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B1%5D=0cd80c8e-5c20-43a8-aa3e-ec701007d443&filter%5Bmodule_categories_uuid%5D%5Boperator%5D=IN&filter%5Bmodule_categories_uuid%5D%5Bpath%5D=module_categories_uuid&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'filters4.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B0%5D=0cd80c8e-5c20-43a8-aa3e-ec701007d443&filter%5Bmodule_categories_uuid%5D%5Boperator%5D=IN&filter%5Bmodule_categories_uuid%5D%5Bpath%5D=module_categories_uuid&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B0%5D=089406ad-304d-4737-80d1-2f08527ae49e&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B1%5D=cee844e2-68b5-489d-bafa-6a0ade2b6dfd&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B2%5D=09a378d2-fd35-41f3-bff0-10d9801741a4&filter%5Bmaintenance_status_uuid%5D%5Boperator%5D=IN&filter%5Bmaintenance_status_uuid%5D%5Bpath%5D=maintenance_status_uuid&filter%5Bsecurity_coverage%5D%5Bvalue%5D%5B0%5D=covered&filter%5Bsecurity_coverage%5D%5Boperator%5D=IN&filter%5Bsecurity_coverage%5D%5Bpath%5D=security_coverage&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'filters5.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B0%5D=68428c33-1db7-438d-b1b3-e23004e0982b&filter%5Bmodule_categories_uuid%5D%5Boperator%5D=IN&filter%5Bmodule_categories_uuid%5D%5Bpath%5D=module_categories_uuid&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'filters6.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=12&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B0%5D=086cebcf-200f-4c34-886e-f9921919b292&filter%5Bmodule_categories_uuid%5D%5Bvalue%5D%5B1%5D=68428c33-1db7-438d-b1b3-e23004e0982b&filter%5Bmodule_categories_uuid%5D%5Boperator%5D=IN&filter%5Bmodule_categories_uuid%5D%5Bpath%5D=module_categories_uuid&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'filters7.json',
    "/index/project_modules?filter%5Bstatus%5D=1&filter%5Btype%5D=project_module&filter%5Bproject_type%5D=full&page%5Blimit%5D=24&page%5Boffset%5D=0&include=field_module_categories%2Cfield_maintenance_status%2Cfield_development_status%2Cuid%2Cfield_project_images&filter%5Bcore_semver_minimum%5D%5Boperator%5D=%3C%3D&filter%5Bcore_semver_minimum%5D%5Bpath%5D=core_semver_minimum&filter%5Bcore_semver_maximum%5D%5Boperator%5D=%3E%3D&filter%5Bcore_semver_maximum%5D%5Bpath%5D=core_semver_maximum&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B0%5D=089406ad-304d-4737-80d1-2f08527ae49e&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B1%5D=cee844e2-68b5-489d-bafa-6a0ade2b6dfd&filter%5Bmaintenance_status_uuid%5D%5Bvalue%5D%5B2%5D=09a378d2-fd35-41f3-bff0-10d9801741a4&filter%5Bmaintenance_status_uuid%5D%5Boperator%5D=IN&filter%5Bmaintenance_status_uuid%5D%5Bpath%5D=maintenance_status_uuid&filter%5Bsecurity_coverage%5D%5Bvalue%5D%5B0%5D=covered&filter%5Bsecurity_coverage%5D%5Boperator%5D=IN&filter%5Bsecurity_coverage%5D%5Bpath%5D=security_coverage&filter%5Bn_security_coverage%5D%5Bvalue%5D%5B0%5D=revoked&filter%5Bn_security_coverage%5D%5Boperator%5D=NOT%20IN&filter%5Bn_security_coverage%5D%5Bpath%5D=security_coverage" => 'items_per_page.json',
  ];

  /**
   * Endpoints for non-jsonapi information.
   *
   * @const array
   */
  const DRUPALORG_ENDPOINT_TO_FIXTURE_MAP = [
    '/drupalorg-api/project-browser-filters?drupal_version=' . \Drupal::VERSION => 'project-browser-filters.json',
    '/drupalorg-api/project-browser-filters?drupal_version=9.0.5' => 'project-browser-filters-error.json',
  ];

  /**
   * Constructor for settings form.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key value store.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly StateInterface $state,
  ) {
  }

  /**
   * Invoked method that returns a promise.
   *
   * The `$actual_api_endpoint` represents the endpoint to fetch the JSON data.
   * This is used to generate all the fixtures manually.
   * For each endpoint, the relevant path is generated by removing certain query
   * parameters (e.g., semver filters).
   *
   * The `$relevant_path` is then used as a key to retrieve specific fixture
   * paths from the `$path_to_fixture` array. This path-to-fixture mapping
   * contains pre-generated JSON responses from $actual_api_endpoint, that are
   * used in various test scenarios. These fixture files simulate responses as
   * if they were real-time API results, providing controlled and predictable
   * data to validate functionality.
   */
  public function __invoke(): \Closure {
    return function ($handler) {
      return function (RequestInterface $request, array $options) use ($handler) {
        $json_response = '';
        // This endpoint, when accessed in a browser, returns the JSON data
        // which is used to generate the fixtures used in
        // ProjectBrowserUiTestJsonApi test.
        $actual_api_endpoint = (string) $request->getUri();
        if (strpos($actual_api_endpoint, DrupalDotOrgJsonApi::JSONAPI_ENDPOINT) !== FALSE) {
          $relevant_path = str_replace(DrupalDotOrgJsonApi::JSONAPI_ENDPOINT, '', $actual_api_endpoint);
          // Remove semver query as it is core version dependent.
          // Processed query will act as relevant path to fixtures.
          $relevant_path = (string) preg_replace('/&filter%5Bcore_semver_minimum%5D%5Bvalue%5D=[0-9]*/', '', $relevant_path);
          $relevant_path = (string) preg_replace('/&filter%5Bcore_semver_maximum%5D%5Bvalue%5D=[0-9]*/', '', $relevant_path);
          $path_to_fixture = self::DRUPALORG_JSONAPI_ENDPOINT_TO_FIXTURE_MAP;
          if (isset($path_to_fixture[$relevant_path])) {
            $module_path = $this->moduleHandler->getModule('project_browser')->getPath();
            if ($data = file_get_contents($module_path . '/tests/fixtures/drupalorg_jsonapi/' . $path_to_fixture[$relevant_path])) {
              $json_response = new Response(200, [], $data);
              return new FulfilledPromise($json_response);
            }
          }

          throw new \Exception('Attempted call to the Drupal.org jsonapi endpoint that is not mocked in middleware: ' . $relevant_path);
        }
        // Other queries to the non-jsonapi endpoints.
        elseif (strpos($actual_api_endpoint, DrupalDotOrgJsonApi::DRUPAL_ORG_ENDPOINT) !== FALSE) {
          $relevant_path = str_replace(DrupalDotOrgJsonApi::DRUPAL_ORG_ENDPOINT, '', $actual_api_endpoint);
          $path_to_fixture = self::DRUPALORG_ENDPOINT_TO_FIXTURE_MAP;

          $is_outdated = $this->state->get('project_browser:test_deprecated_api');
          if (
            strpos($relevant_path, '/drupalorg-api/project-browser-filters?drupal_version=') !== FALSE &&
            $is_outdated
          ) {
            // Force the wrong Drupal version.
            $relevant_path = '/drupalorg-api/project-browser-filters?drupal_version=9.0.5';
          }

          if (isset($path_to_fixture[$relevant_path])) {
            $module_path = $this->moduleHandler->getModule('project_browser')->getPath();
            if ($data = file_get_contents($module_path . '/tests/fixtures/drupalorg_jsonapi/' . $path_to_fixture[$relevant_path])) {
              $json_response = new Response(200, [], $data);
              return new FulfilledPromise($json_response);
            }
          }

          throw new \Exception('Attempted call to the Drupal.org endpoint that is not mocked in middleware: ' . $relevant_path);
        }

        return $handler($request, $options);
      };
    };
  }

}
