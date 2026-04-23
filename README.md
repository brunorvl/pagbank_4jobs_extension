> [!CAUTION]
> **ESTA VERSÃO É EXPERIMENTAL.**
> Não utilize em produção sem realizar testes exaustivos no modo Sandbox.
> O autor não se responsabiliza por transações não processadas durante o período de testes.

# PagBank Connect para OpenCart (Boleto & Cartão)

Esta extensão integra o gateway de pagamento **PagBank (PagSeguro)** ao OpenCart, focando na estabilidade das transações e na melhor experiência para o usuário, especialmente em pagamentos via **Boleto Bancário**.

## 🚀 O que esta extensão resolve?

Esta versão foi criada para oferecer:
1. **Venda transparente de Boleto e Cartões de Crédito:** O cliente finaliza o pagamento sem sair da sua loja.
2. **Solução Anti-Bloqueio:** Exibição do link do boleto na página de sucesso para contornar bloqueadores de pop-up.

## 📋 Compatibilidade

* **OpenCart:** 2.3.x, 3.0.x e 4.x (via ajustes de template).
* **PHP:** 7.4, 8.0, 8.1 ou superior.
* **API:** PagBank API (Orders).

## 🔧 Estrutura do Projeto

* `catalog/controller/extension/payment/pagbank.php`: Lógica de integração e comunicação com a API.
* `catalog/view/theme/default/template/extension/payment/pagbank.twig`: Interface de pagamento com suporte a cartão criptografado.
* `pagbank_boleto_success.ocmod.xml`: Modificação para exibir o link do boleto na página de agradecimento.

## 📥 Instalação

### 1. Upload dos Arquivos
Suba as pastas `system`, `catalog` e `admin` para a raiz da sua instalação OpenCart via FTP ou Gerenciador de Arquivos.
> [!IMPORTANT]
> **NÃO SUBIR A PASTA "UPLOAD":** Você deve subir apenas o **conteúdo** que está dentro da pasta upload para a raiz da sua loja.

### 2. Ativação e Modificação (OCMOD)
Esta é a parte crucial para o funcionamento do link do boleto na página final:
1. Vá em **Extensões > Pagamentos > PagBank** e clique no botão para instalar a extensão.
2. Se enviou o arquivo XML para a pasta `system`, vá em **Extensões > Modificações** e clique no botão azul **Atualizar (Refresh)** no topo superior direito.

### 3. Configuração no Painel Admin
1. Vá em **Extensões > Pagamentos > PagBank**.
2. Insira seu **Token** de acesso (gerado no painel do PagBank).
3. Ative o modo **Sandbox** para testes ou **Produção** para vendas reais.
4. Configure os status de pedido para cada retorno da API.

## 🛠️ Funcionamento Técnico

### Debugging
A extensão inclui logs detalhados para o modo Sandbox, permitindo visualizar o `payload_json` enviado e a `response` recebida diretamente no console de rede do navegador durante o desenvolvimento (o código de depuração está presente no controller).

## ⚠️ Homologação Obrigatória (Importante!)

Antes de alternar para o modo **Produção**, o PagBank exige que você realize o processo de homologação técnica. Sem isso, suas chaves de produção podem não funcionar corretamente.

1. **Testes em Sandbox:** Realize ao menos uma transação completa de Cartão e uma de Boleto utilizando o ambiente de Sandbox.
2. **Formulário de Homologação:** Após validar que tudo funciona em ambiente de teste, você deve preencher o formulário oficial do PagBank para solicitar a liberação das credenciais de produção.
3. **Liberação:** O time técnico do PagBank analisará os logs de teste e liberará sua conta para transacionar valores reais.

> **Link Útil:** [Documentação de Homologação PagBank](https://dev.pagbank.uol.com.br/docs/homologacao)

---
# pagbank_4jobs_extension
