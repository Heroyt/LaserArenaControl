<?php

namespace App\Gate\Screens;

use App\Gate\Settings\GateSettings;

/**
 * @template T of GateSettings
 */
interface WithSettings
{
    /**
     * Get the Latte template for the settings form.
     *
     * @return string
     */
    public static function getSettingsForm() : string;

    /**
     * Process the settings form submission
     *
     * Builds the settings object from the request values.
     *
     * @param  array<string, mixed>  $data
     * @return T
     */
    public static function buildSettingsFromForm(array $data) : GateSettings;

    /**
     * Return a settings DTO.
     *
     *  If settings was not already set, it should create a new instance with default values.
     *
     * @return T
     */
    public function getSettings() : GateSettings;

    /**
     * Set screen settings.
     *
     * @param  T  $settings
     *
     * @return $this
     */
    public function setSettings(GateSettings $settings) : static;
}
