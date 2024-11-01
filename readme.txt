=== Virtuaria eRede - Pix e Crédito ===
Contributors: tecnologiavirtuaria
Tags: itaú, rede, cartão, pix, pagamentos
Requires at least: 4.7
Tested up to: 6.6.2
Stable tag: 1.0.0 
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Permite pagamentos via Pix e Cartão de Crédito na sua loja Woocommerce. Integração com eRede (Itaú), reembolsos total ou parcial, parcelamento, etc

== Description ==

Fácil de instalar e configurar, permite pagamento no Cartão de Crédito ou Pix em sua loja virtual Woocommerce. O plugin de pagamento Virtuaria para eRede / Itaú, também permite reembolso total e parcial, desconto no pix, confirmação automática de pagamentos, parcelamento configurável, entre muitas outras coisas.

Aceita as principais bandeiras de cartão (Mastercard, Visa, Hiper, Elo, Diners, Sorocred, American Express, Hipercard, JCB, Banescard, Cabal, Mais, Credz, Visa e Elo).

Permite realizar pagamentos sem sair do site (checkout transparente). Fornece relatórios detalhados de transações, automação de status de pedidos e captura de transações de forma flexível conectando você à Rede, garantindo transações rápidas e seguras com a confiabilidade do Itaú e da Rede.


= ⭐ Principais Recursos =
* Suporte a Crédito;
* Suporte a Pix;
* Opção de parcelamento com ou sem juros (configurável no plugin);
* Reembolso (total e parcial);
* Modo de processamento (síncrono ou assíncrono) do checkout;
* Checkout Transparente (permite fazer o pagamento sem sair do site);
* Relatório (log) para consulta a detalhes de transações, incluindo erros;
* Identificação na fatura para pagamentos via cartão (exibir na fatura);
* Mudança automática dos status dos pedidos (aprovado, negado, cancelado, etc);
* Detalhamento nas notas do pedido das operações ocorridas durante a comunicação com a Rede (reembolsos, parcelamentos, mudanças de status e valores recebidos/cobrados);
* Flexibilidade de captura de transação (escolha entre captura automática ou apenas autorização)


### ⚡ **Otimização do Checkout**
O plugin possui uma configuração para ativar o modo de processamento assíncrono do pedido. Isso permite que algumas das atualizações de status que ocorrem durante a finalização da compra, sejam feitas em segundo plano e de forma assíncrona, acelerando significativamente o checkout. Recomendamos ativar somente se seus clientes costumam comprar muitos itens de uma vez e isto esteja deixando o seu checkout lento.


### 🔐 **Política de Privacidade e Termos de Serviço**

Em nosso compromisso com a privacidade e transparência, priorizamos a proteção dos dados e a clareza em nossas políticas de uso. 
[Política de Privacidade da Rede](https://www.userede.com.br/n/seguranca)
[Política de Privacidade da Virtuaria](https://virtuaria.com.br/politica-de-privacidade-para-plugins-erede/)


### 🌍 **Serviços Externos** 

Para uma integração robusta e confiável com a Rede, é essencial consumir os serviços oferecidos por suas APIs. Tanto o endpoint de produção (https://api.userede.com.br) quanto o de homologação (https://sandbox-erede.useredecloud.com.br) fornecem acesso às funcionalidades necessárias para realizar pagamentos, reembolsos e muito mais, diretamente através da API da Rede.


= 🛠 Instalação: =

1 - Acesse o menu Virtuaria eRede, defina o PV (número de filiação) e chave de integração obtidos no portal da conta rede e clique em salvar;
2 - Acesse o Menu Woocommerce > Configurações > Pagamentos e Adicione o Virtuaria eRede Crédito e/ou Pix;

Com estes passos o cálculo de frete já estará disponível em sua loja virtual.

= Compatibilidade =

Requer WooCommerce 4.0 ou posterior para funcionar.
Wordpress WPMU.

== Installation ==

= Instalação do plugin: =

- Envie os arquivos do plugin para a pasta wp-content/plugins, ou instale usando o instalador de plugins do WordPress.
- Ative o plugin.
- Para utilização de pagamentos Pix é necessário cadastrar uma chave pix e URL de notificações, abaixo seguem os procedimentos:

**Cadastro de chave Pix**
Para habilitar sua chave Itaú para transacionar na Rede:

Acesse o portal [userede.com.br](https://userede.com.br);
Efetue seu login;
Acesse a rota: Para vender > PIX > Clique em “quero utilizar o Pix” > Aceite de termos de uso > Selecione agência e conta.


**Cadastro de URL**
O cadastro dessa URL será por CNPJ, independente de quantos ou quais PV's foram habilitados para aquele estabelecimento.

Para esse cadastro, o estabelecimento deve ligar na central de atendimento nos telefones: Central de atendimento: Capitais e regiões metropolitanas 4001 4433 ou Central de atendimento: Demais localidades 0800 728 4433 e informar o número de CNPJ, PV, email para contato e a URL que deseja utilizar para receber as notificações do Pix. O prazo para ativação é de 2 dias uteis após a abertura do chamado.

A URL base para notificações Pix é exibida na tela Integração da configuração do plugin.

= Requerimentos: =

- Conta cadastrada no portal Use Rede;
- Para modalidade Pix, é necessário possuir chave Pix cadastrada no portal e contactar o suporte da Rede para cadastro a URL de notificações(descrição na guia instalação);
- Woocommerce 4.0+.

Este plugin foi desenvolvido de forma independente da Rede (itaú). Nenhum dos desenvolvedores deste plugin possuem vínculos com esta empresa.

== Frequently Asked Questions ==

= 1 - Qual é a licença do plugin? =

Este plugin está licenciado como GPL3 ou superior.

= 2 - O que eu preciso para utilizar este plugin? =

* WooCommerce 4.0 ou posterior.

* Ter instalado uma versão atual do plugin Virtuaria Correios ou WooCommerce Extra Checkout Fields for Brazil.

* Conta e contrato com a Rede.

= 3 - Quais são os métodos de pagamento que o plugin aceita? =

São aceitos os métodos de pagamento Crédito e Pix.

= 4 - Como que o plugin faz integração com a Rede? =
Fazemos a integração baseada na documentação oficial da Rede que pode ser encontrada nos "[guias de integração](https://developer.userede.com.br/e-rede#primeiros-passos)utilizando a última versão da API de pagamentos.

= 5 - Tem confirmação automática dos pagamentos? O status do pedido é alterado automaticamente? =
Sim, o status é alterado automaticamente usando a API de notificações de mudança de status da Rede.

= 6 - Situações comuns para bloqueio no recebimento de notificações da Rede pelo plugin =
O motivo mais comum é algum plugin de segurança, firewall ou ferramenta no servidor onde a loja está rodando estar bloqueando as notificações. Neste caso, basta desativar o bloqueio ou incluir uma exceção para não barrar as notificações que tem a Rede como origem.

Exemplos:

* Site com CloudFlare, pois por padrão serão bloqueadas quaisquer comunicações de outros servidores com o seu. É possível resolver isso desbloqueando a lista de IPs da Rede.

* Plugin de segurança como o "iThemes Security" com a opção para adicionar a lista do HackRepair.com no .htaccess do site.

= 7 - Este plugin permite o reembolso total e parcial da venda? =
Sim, você pode reembolsar pedidos com status processando indo direto a página do pedido no woocommerce e clicar em Reembolso -> Reembolso via Virtuaria eRede e setar o valor seja ele total ou parcial. Contudo, por restrição da Rede, reembolsos parciais só estarão disponíveis 24h após a compra.

= 8 - Pedidos no Pix sendo Cancelado =
Quando uma compra é feita via pagamento com Pix, é criado um pedido com status “Aguardando” no painel, porém, caso o pagamento do Pix não seja identificado até o tempo limite, o pedido mudará para o status “Cancelado” automaticamente. O tempo limite é definido no campo “Validade do Código PIX” na tela de configurações do plugin (existe uma tolerância de 30 min, além do tempo limite).

= 9 - Método de pagamento não aparece? =
No momento o plugin não é compatível com o checkout em blocos do woocommerce, para que os métodos sejam exibidos é necessário alterar o checkout para o modo Clássico (Shortcode) em sua loja virtual.


== Screenshots ==
1. Configuração - Integração;
2. Checkout Crédito;
3. Venda com Pix;
4. Histórico pedido Pix;
5. Configuração do Pix;
6. Configuração do Crédito.


== Changelog ==
= 1.0.0 - 2024-10-22 =
* Versão Inicial.


== Upgrade Notice ==
Nenhuma atualização disponível.