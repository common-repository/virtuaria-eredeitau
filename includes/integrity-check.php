<?php
/**
 * Check plugin integrity.
 *
 * @package Virtuaria/Integrations/Erede.
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_plugin_active( 'virtuaria-erede/virtuaria-erede.php' )
	|| 'Virtuaria eRede - Pix e Crédito' !== $plugin_data['Name']
	|| '<a href="https://virtuaria.com.br/">Virtuaria</a>' !== $plugin_data['Author'] ) {
	wp_die( 'Erro: Plugin corrompido. Favor baixar novamente o código e reinstalar o plugin.' );
}
