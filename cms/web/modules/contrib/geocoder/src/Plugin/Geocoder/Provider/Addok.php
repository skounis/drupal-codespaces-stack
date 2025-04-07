<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a Addok geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "addok",
 *   name = "Addok",
 *   handler = "\Geocoder\Provider\Addok\Addok",
 *   arguments = {
 *     "rootUrl" = "https://api-adresse.data.gouv.fr"
 *   }
 * )
 */
class Addok extends ConfigurableProviderUsingHandlerWithAdapterBase {}
