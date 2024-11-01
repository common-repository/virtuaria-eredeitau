<?php
/**
 * Template Erede Multsite settings.
 *
 * @package virtuaria/integrations/erede.
 */

defined( 'ABSPATH' ) || exit;

?>

<h1 class="main-title">Virtuaria eRede</h1>

<?php
if ( isset( $_GET['page'], $_POST['erede_nonce'] )
	&& in_array( $_GET['page'], array( 'virtuaria-erede', 'virtuaria-erede-premium' ), true )
	&& wp_verify_nonce(
		sanitize_text_field( wp_unslash( $_POST['erede_nonce'] ) ),
		'update-erede-settings'
	)
) {
	echo wp_kses_post(
		'<div class="notice notice-success is-dismissible"><p>Configurações salvas com sucesso.</p></div>'
	);
}
?>

<form action="" method="post" id="mainform" class="main-setting">
	<div class="navigation-tab">
		<a class="tablinks integration active" href="#">Integração</a>
		<a class="tablinks pix" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=virtuaria_erede_pix' ) ); ?>">Pix</a>
		<a class="tablinks credit" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=virtuaria_erede_credit' ) ); ?>">Crédito</a>
		<!-- <a class="tablinks premium" href="../wp-admin/admin.php?page=virtuaria-erede-premium">Premium</a> -->
	</div>
	<table class="form-table integration">
		<tbody>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="woocommerce_virt_erede_environment">Ambiente</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>Ambiente</span></legend>
						<select
							name="woocommerce_virt_erede_environment"
							id="woocommerce_virt_erede_environment">
							<option
							<?php
							if ( isset( $options['environment'] ) ) {
								echo selected( 'sandbox', $options['environment'], false );
							}
							?>
							value="sandbox">Testes</option>
							<option
							<?php
							if ( isset( $options['environment'] ) ) {
								echo selected( 'production', $options['environment'], false );
							}
							?>
							value="production">Produção</option>
						</select>
						<p class="description">
							Modo de execução da integração com erede.
						</p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="woocommerce_virt_erede_pv">Número de filiação (PV)</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>Número de filiação (PV)</span></legend>
						<input
							type="text"
							name="woocommerce_virt_erede_pv"
							id="woocommerce_virt_erede_pv"
							value="<?php echo isset( $options['pv'] ) ? esc_attr( $options['pv'] ) : ''; ?>" />
						<p class="description">
							Define o PV usado na comunição com os servidores eREDE.
						</p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="woocommerce_virt_erede_integration_key">Chave de integração</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>Chave de integração</span></legend>
						<input
							type="text"
							name="woocommerce_virt_erede_integration_key"
							id="woocommerce_virt_erede_integration_key"
							value="<?php echo isset( $options['integration_key'] ) ? esc_attr( $options['integration_key'] ) : ''; ?>" />
						<p class="description">
							Define a chave de integração(token) usada no processamento das transações.
						</p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="woocommerce_virt_erede_process_mode">Modo de processamento </label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>Modo de processamento</span></legend>
						<select class="select " name="woocommerce_virt_erede_process_mode" id="woocommerce_virt_erede_process_mode">
							<option value="sync" <?php selected( 'sync', isset( $options['process_mode'] ) ? $options['process_mode'] : '' ); ?>>Síncrono</option>
							<option value="async" <?php selected( 'async', isset( $options['process_mode'] ) ? $options['process_mode'] : '' ); ?>>Assíncrono</option>
						</select>
						<p class="description">
							A mudança de status do pedido dispara uma série de ações, como envio de emails, redução do estoque, eventos em plugins, entre muitas outras. No modo assíncrono, o checkout não precisa esperar pela conclusão destas ações,  consequentemente fica mais rápido. A confirmação do pagamento via Cartão de Crédito ocorre da mesma forma, independente do modo escolhido. Apenas a mudança de status do pedido é afetada, pois passa a ocorrer via agendamento (cron) em até 5 minutos após a finalização da compra pelo cliente.
						</p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					URL Callback (Webhook)
				</th>
				<td class="forminp">
					<code><?php echo esc_url( home_url( 'wc-api/WC_Virtuaria_eRede_Gateway' ) ); ?></code>
					<p>
						URL de retorno padrão para as notificações transacionais da rede.
					</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc section">
					Depuração
				</th>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="woocommerce_virt_correios_debug">Debug</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>Debug</span></legend>
						<input
							type="checkbox"
							name="woocommerce_virt_correios_debug"
							id="woocommerce_virt_correios_debug"
							value="yes"
							<?php isset( $options['debug'] ) ? checked( $options['debug'], 'yes' ) : ''; ?> />
						<p class="description">
							Log para depuração de problemas. Clique <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&source=virtuaria-eredeitau' ) ); ?>">aqui</a> para ver o log de depuração.
						</p>
					</fieldset>
				</td>
			</tr>
		</tbody>
	</table>
	<?php wp_nonce_field( 'update-erede-settings', 'erede_nonce' ); ?>
	<input
		type="submit"
		class="button button-primary"
		value="<?php esc_attr_e( 'Salvar alterações', 'virtuaria-erede' ); ?>">
</form>
