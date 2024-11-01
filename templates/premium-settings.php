<?php
/**
 * Template Erede Multsite settings.
 *
 * @package virtuaria/integrations/erede.
 */

defined( 'ABSPATH' ) || exit;

?>

<h1 class="main-title">Virtuaria Erede</h1>

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
		<a class="tablinks integration" href="admin.php?page=virtuaria-erede">Integração</a>
		<a class="tablinks pix" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=virtuaria_erede_pix' ) ); ?>">Pix</a>
		<a class="tablinks credit" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=virtuaria_erede_credit' ) ); ?>">Crédito</a>
		<a class="tablinks premium active" href="#">Premium</a>
	</div>
	<table class="form-table premium">
		<tbody>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="woocommerce_virt_erede_serial">Código de Licença</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>Código de Licença</span></legend>
						<input
							type="text"
							name="woocommerce_virt_erede_serial"
							id="woocommerce_virt_erede_serial"
							value="<?php echo isset( $options['serial'] ) ? esc_attr( $options['serial'] ) : ''; ?>" />
						<p class="description">
							Informe o código de licença para ter acesso a todos os recursos <b>premium</b> do plugin.
						</p>
					</fieldset>
				</td>
			</tr>
			<?php
			if ( ! isset( $options['serial'], $options['authenticated'] )
				|| ! $options['serial']
				|| ! $options['authenticated'] ) :
				?>
				<tr valign="top">
					<th scope="row" class="titledesc">
						Status
					</th>
					<td>
						<p class="description">
							<b><span style="color:red">Desativado</span></b><br>
							Você ainda não possui um Código de Licença válido. É possível adquirir através do link <a href="https://virtuaria.com.br/loja/virtuaria-erede/" target="_blank">https://virtuaria.com.br/loja/virtuaria-erede</a>. Em caso de dúvidas, entre em contato com o suporte via e-mail <a href="mailto:integracaoerede@virtuaria.com.br">integracaoerede@virtuaria.com.br</a>.
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc" style="width: 0;">
						
					</th>
					<td>
						<div class="premium-disabled form-table hidden premium">
							<h2>Recursos Premium</h2>
							<p class="description">
								Com nossa versão premium, você terá acesso a funcionalidades avançadas que vão melhorar a gestão de pagamentos. Um plugin confiável e poderoso, capaz de transformar a gestão de pagamentos de seu e-commerce. Invista no nosso plugin premium e maximize o potencial do seu e-commerce! Confira abaixo a lista de recursos disponíveis: 
							</p>

							<ul>
								<li><h3>💡 Pagamentos via Pix</h3> Receba pagamentos no Pix de forma rápida e com confirmação automática do pagamento em sua loja.</li>
							</ul>
						</div>
					</td>
				<?php
			else :
				?>
				<tr valign="top">
					<th scope="row" class="titledesc section">
						Recursos Premium
					</th>
					<td>
						<p class="description">
							<b>Status: <span style="color:green">Ativado</span></b><br>
							Você possui uma chave de acesso válida. Em caso de dúvidas, entre em contato com o suporte via e-mail <a href="mailto:integracaoerede@virtuaria.com.br">integracaoerede@virtuaria.com.br</a>.
						</p>
						<h1 class="premium-resources">
							Confira abaixo a lista de recursos disponíveis: 
						</h1>
						<ul>
							<li>
								<h3>💡 Pagamentos via Pix</h3> Receba pagamentos no Pix de forma rápida e com confirmação automática do pagamento em sua loja.
								<img src="<?php echo esc_attr( VIRTUARIA_EREDE_URL ); ?>admin/images/pix.jpg" alt="Pagamento com Pix">
							</li>
						</ul>
					</td>
				</tr>
				<?php
			endif;
			?>
		</tbody>
	</table>
	<?php wp_nonce_field( 'update-erede-settings', 'erede_nonce' ); ?>
	<input
		type="submit"
		class="button button-primary"
		value="<?php esc_attr_e( 'Salvar alterações', 'virtuaria-erede' ); ?>">
</form>
