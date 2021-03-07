<?php
/**
 * @param $id
 * @param string $plugin
 * @param string|object $params
 * @return string
 */
function mergusergetstring($id, $plugin = '', $params = ''): string {
    try {
        return get_string($id, $plugin, $params) ?? '';
    } catch (coding_exception $e) {
        return '';
    }
}
