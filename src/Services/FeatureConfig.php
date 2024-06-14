<?php

namespace App\Services;

use Lsr\Core\Config;

/**
 * Service for checking if certain features are enabled in config (ENV)
 */
class FeatureConfig
{

    /** @var array<string,bool> */
    private array $features = [];

    /**
     * @param  Config  $config
     * @param  array<string,bool>  $default
     */
    public function __construct(
      Config $config,
      array  $default = [],
    ) {
        foreach ($default as $key => $value) {
            $this->features[strtolower($key)] = (bool) $value;
        }

        // Initialize feature array from config
        foreach ($config->getConfig('ENV') as $key => $value) {
            if (str_starts_with($key, 'FEATURE_')) {
                /** @var string $key */
                $key = strtolower(str_replace('FEATURE_', '', $key));
                $this->features[$key] = (is_int($value) && $value > 0) || (is_string($value) && strtolower(
                      $value
                    ) === 'true');
            }
        }
    }

    public function isFeatureEnabled(string $feature) : bool {
        $feature = strtolower($feature);
        return isset($this->features[$feature]) && $this->features[$feature];
    }

    /**
     * @return array<string,bool>
     */
    public function getFeatures() : array {
        return $this->features;
    }

}