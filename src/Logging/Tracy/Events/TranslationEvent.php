<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\Logging\Tracy\Events;

class TranslationEvent
{

	public string  $message = '';
	public ?string $plural  = null;
	public ?string $context = null;
	public string  $source  = '';

}