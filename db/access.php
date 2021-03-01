<?php

$capabilities = [
	'tool/mergeusers:mergeusers' => [
		'riskbitmask' => RISK_DATALOSS,
		'captype' => 'write',
		'contextlevel' => CONTEXT_SYSTEM,
		'archetypes' => [
			'manager' => CAP_ALLOW,
		],
		'clonepermissionsfrom' => 'moodle/user:delete'
	],
];
