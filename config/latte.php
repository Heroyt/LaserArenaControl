<?php
return [

	'tags' => [
		// name => [startTag, endTag]
		'logo'         => [
			'echo \Lsr\Core\App::getLogoHtml()',
		],
		'link'         => [
			'echo \Lsr\Core\App::getLink(%node.args)',
		],
		'getUrl'       => [
			'echo \Lsr\Core\App::getUrl()',
		],
		'csrf'         => [
			'echo formToken()',
		],
		'csrfInput'    => [
			'$type = %node.word; echo \'<input type="hidden" name="_csrf_token" value="\'.hash_hmac(\'sha256\', $type, formToken($type)).\'" />\'',
		],
		'alert'        => [
			'echo alert(%node.args)',
		],
		'alertDanger'  => [
			'echo alert(%node.args, "danger")',
		],
		'alertSuccess' => [
			'echo alert(%node.args, "success")',
		],
		'alertWarning' => [
			'echo alert(%node.args, "warning")',
		],
		'lang'         => [
			'echo lang(%node.args)',
		],
		'tracyDump'    => [
			'echo \Tracy\Dumper::toHtml(%node.args)',
		],
		'svgIcon'      => [
			'echo svgIcon(%node.args)',
		],
	],


	'filters' => [
		// name => callback
		'lang' => 'lang',
	],

];
