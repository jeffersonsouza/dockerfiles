# dockerfiles

Imagens Nginx e PHP-FPM publicadas em `jeffersonsouza/nginx` e `jeffersonsouza/php`.

## Atualização de segurança de 21/10/2026

| Série | PHP na imagem | Tag publicada |
| --- | --- | --- |
| 8.2 | 8.2.35 | `jeffersonsouza/php:8.2-nginx` |
| 8.3 | 8.3.36 | `jeffersonsouza/php:8.3-nginx` |
| 8.4 | 8.4.27 | `jeffersonsouza/php:8.4-nginx` |
| 8.5 | 8.5.12 | `jeffersonsouza/php:8.5-nginx` |

Esses releases corrigem **CVE-2026-109618 e CVE-2026-109619**, conforme o [aviso oficial do PHP](https://www.php.net/archive/2026.php#2026-10-21-5).

As séries 8.2–8.5 continuam usando **`jeffersonsouza/nginx:alpine-3.23` como base final**, preservando seu Nginx, módulos, configurações, arquivos e usuário personalizados. O PHP é preparado em um estágio separado baseado em `php:<patch>-fpm-alpine3.23`; seu runtime e suas extensões são copiados para a imagem personalizada, com as dependências APK correspondentes. O build confere os hashes do binário Nginx e de todos os módulos antes e depois da instalação dessas dependências, falhando caso algum deles seja substituído.

Os índices Alpine consultados em 21/10/2026 ainda ofereciam PHP 8.2.33 no Alpine 3.22 e PHP 8.3.31/8.4.21/8.5.6 no Alpine 3.23; `apk upgrade` sozinho não entregava as correções. A versão é conferida durante o build e no teste da imagem, que falham se o PHP estiver abaixo do mínimo corrigido ou pertencer a outra série. Os dois estágios usam Alpine 3.23 para manter compatibilidade de bibliotecas.

Os Dockerfiles PHP não reinstalam Nginx. Eles aplicam os quatro arquivos HTTP compartilhados deste repositório à base personalizada, habilitam a integração com FPM e fazem o cache respeitar respostas privadas, cookies e autenticação. O instalador de extensões é fixado por digest. Para atualizar PHP futuramente, altere o patch no Dockerfile e os mínimos do teste em `.github/scripts/smoke-test-php.sh`.

## Executar pipelines manualmente

Todos os workflows têm `workflow_dispatch`. Após incorporar os arquivos na branch padrão, abra **Actions → workflow → Run workflow**. [O GitHub exige o workflow na branch padrão para habilitar esse evento](https://docs.github.com/en/actions/using-workflows/events-that-trigger-workflows#workflow_dispatch).

Nos sete workflows de imagens, a opção `publish` permite testar sem publicar. A publicação exige `publish=true` e execução em `master` ou em uma tag `v*.*.*`. PRs e `develop` apenas validam e não sobrescrevem as tags estáveis. Os secrets continuam sendo `DOCKER_USER` e `DOCKER_PASSWORD`.

Para reconstruir e publicar as quatro séries com GitHub CLI:

```sh
for version in 82 83 84 85; do
  gh workflow run "docker-publish-php${version}.yml" --ref master -f publish=true
done
```

Se também houver mudanças em `new/nginx`, publique primeiro a base `jeffersonsouza/nginx:alpine-3.23` pelo workflow **NGINX** e depois execute os workflows PHP. Eles herdam o binário, módulos e demais arquivos da imagem publicada; os quatro arquivos HTTP compartilhados são atualizados a partir do repositório durante o build PHP.

As pipelines PHP 8.2–8.5 constroem sem cache, buscando a base atual, e testam **amd64 e arm64** antes do login no Docker Hub e da publicação. O build final reutiliza as camadas já testadas no mesmo job. Os testes cobrem versão, extensões, configurações, Nginx → FPM, página inicial sem `phpinfo()`, respostas privadas, cookies, autenticação e desligamento do contêiner.

Os filtros de caminhos evitam builds sem alterações relevantes. As actions são fixadas por SHA e as permissões dos builds se limitam à leitura do repositório.

O workflow `Issue closer` também pode ser executado manualmente, informando `issue_number`. Ele mantém a regra existente de fechar issues cujo corpo não contenha `guidelines for Contributing`, usando a action oficial `github-script`. Essa regra foi herdada de outro projeto e merece revisão antes de continuar sendo usada.

## Build e teste local

O contexto dos Dockerfiles é **`new/`**; os arquivos de configuração PHP são referenciados a partir desse diretório:

```sh
docker buildx build --pull --no-cache --load \
  --file new/8.5-fpm/Dockerfile --tag php-local:8.5 ./new
bash .github/scripts/smoke-test-php.sh php-local:8.5 8.5
```

Troque a série no caminho, na tag e no teste para validar 8.2, 8.3 ou 8.4. Para testar outra arquitetura, acrescente `--platform linux/amd64` ou `--platform linux/arm64` ao build; a máquina precisa oferecer a emulação correspondente.

## Compatibilidade e implantação

- A raiz `/` das imagens PHP 8.2–8.5 executa a página de boas-vindas compartilhada de `new/php/`, com imagem local, dados do ambiente e horário gerado pelo PHP em cada requisição. Ela verifica FPM e extensões essenciais, devolve HTTP 200 quando as verificações passam ou 503 quando falham, e usa `Cache-Control: no-store`. Clientes que enviam `Accept: application/json` recebem o diagnóstico em JSON.
- A página HTML inclui seções recolhíveis em inglês com configurações PHP ativas, orientações básicas e uma referência histórica do teste de carga em `new/php/benchmark.json`. Esses resultados não são um teste ao vivo; a resposta JSON continua restrita ao diagnóstico dinâmico.
- O diagnóstico informa versão PHP, SAPI, família do sistema, arquitetura, timezone e nomes das extensões carregadas. Não exibe hostname, IPs, caminhos, credenciais, variáveis de ambiente, cookies, cabeçalhos de requisição nem `phpinfo()`. Versão e módulos continuam sendo informações públicas; substitua o index pelo da aplicação ou restrinja o diagnóstico quando esses metadados não devam ser publicados.
- As tags, o diretório `/var/www/html` e o UID 1000 de `www-data` são preservados.
- PHP e FPM agora usam `/usr/local/bin/php` e `/usr/local/sbin/php-fpm`. Arquivos INI ficam em `/usr/local/etc/php/conf.d/` e pools em `/usr/local/etc/php-fpm.d/`. Atualize volumes e scripts que apontavam para `/etc/php82`, `/etc/php83`, `/etc/php84`, `/etc/php85` ou binários com sufixo de versão.
- A configuração inicial é `php.ini-production`; as extensões existentes são verificadas pelo teste. Não instale pacotes Alpine `php8x-*` nessas imagens: eles introduziriam outro runtime e módulos de ABI diferente.
- Nginx e FPM conversam por `127.0.0.1:9000`. O healthcheck espera `pong` em `/fpm-ping`. O Supervisor gerencia os processos diretamente e permite desligamento gracioso.
- O cache só armazena respostas com prazo explícito informado pela aplicação (`Cache-Control`, `Expires` ou `X-Accel-Expires`). O cache automático de uma hora foi removido. `private`, `no-store`, `Set-Cookie` e `Vary` são respeitados; requisições com cookies ou `Authorization` não usam nem alimentam o cache. GET e HEAD compartilham a mesma chave. O cache lock fica desativado na configuração genérica: ele também retinha requisições `no-store` por até 5 s. Use bloqueio de preenchimento apenas em locations específicas que sejam efetivamente cacheáveis.
- As alterações nos arquivos só chegam ao Docker Hub depois da publicação bem-sucedida. Em cada ambiente consumidor, faça pull e **recrie os contêineres**; reiniciar um contêiner existente não atualiza PHP. Confira `php -v` no contêiner novo.

PHP 8.0/8.1 e as bases Nginx Alpine 3.16/3.19 continuam como legado e não recebem a garantia de correção descrita acima. [PHP 8.2 encerra o suporte de segurança em 31/12/2026](https://www.php.net/supported-versions.php); planeje a migração dos consumidores.

## Segurança e recursos em PHP 8.2–8.5

A base usa um worker Nginx, um worker FPM e OPcache compartilhado de 64 MiB, sem JIT. O limite PHP continua em 128 MiB **por requisição**, não por contêiner. O cache Nginx usa zona de 8 MiB e até 256 MiB em disco. Esses valores reduzem o risco de excesso de concorrência em contêineres pequenos; extensões nativas, buffers, páginas de cache e outros processos também consomem memória fora do limite PHP.

| Limite do contêiner | `PHP_FPM_MAX_CHILDREN` inicial | OPcache | Limite PHP por requisição |
| --- | --- | --- | --- |
| 256 MiB | `1` (padrão da imagem) | 64 MiB | 128 MiB |
| 512 MiB | `2` | 64 MiB | 128 MiB |

Configure o limite real no Docker/orquestrador e, para 512 MiB, defina `PHP_FPM_MAX_CHILDREN=2` no ambiente. A variável é lida pelo FPM na inicialização; não há dimensionamento automático. Antes de aumentar esses valores, meça o RSS dos workers com a aplicação real, filas, latência p95/p99 e o estado do OPcache. Use `/fpm-status` e `/fpm-ping` a partir de dentro do contêiner; ambos são restritos ao loopback. O ping usa o mesmo pool e pode aguardar quando todos os workers estiverem ocupados.

Os testes aceitam os dois perfis:

```sh
bash .github/scripts/smoke-test-php.sh php-local:8.5 8.5 256m 1
bash .github/scripts/smoke-test-php.sh php-local:8.5 8.5 512m 2
```

- FPM aceita somente scripts `.php`, não exibe erros no HTTP e mantém logs dos workers. O timeout de 120 s também cobre trabalho após `fastcgi_finish_request()`. Nginx aguarda até 125 s entre leituras do FPM, evitando cortar uma resposta antes desse limite. I/O PHP usa timeout padrão de 60 s. Aumente esses limites apenas nos endpoints que precisarem.
- Backtraces do slowlog ficam desativados por padrão: a tentativa de `ptrace` falha com as permissões padrão do Docker. Use os tempos total/upstream dos logs para localizar requisições lentas. Captura de stack exige configuração e revisão de permissões específicas do ambiente; a imagem não adiciona capabilities ou desativa o seccomp para isso.
- Upload PHP continua em 32 MiB e POST em 35 MiB; o Nginx das imagens PHP também limita o corpo a 35 MiB. O Nginx independente mantém seu limite de 100 MiB.
- Cookies de sessão usam `HttpOnly` e modo estrito. Em produção acessível por HTTPS, ative também `session.cookie_secure=On` e defina `SameSite` conforme os fluxos de autenticação da aplicação. A imagem não força HTTPS, pois também atende HTTP local e pode ficar atrás de terminação TLS.
- A invalidação exige `PURGE /purge/caminho?query` pelo loopback; acesso externo é negado. A query string faz parte da chave. Não abra essa rota publicamente.
- Dotfiles são bloqueados antes de arquivos estáticos; manifests de dependências e Dockerfiles são negados. Sufixos PHP não executáveis retornam 404, sem servir o código-fonte. Mantenha a raiz pública limitada ao diretório público da aplicação e proíba execução PHP em diretórios de upload no vhost específico.
- Logs usam `$remote_addr`, nunca o `X-Real-IP` informado diretamente pelo cliente, e incluem tempos total/upstream. O PHP recebe `X-Forwarded-For`/`X-Real-IP` derivados desse endereço. Para recuperar o IP original atrás de um proxy, configure `set_real_ip_from` **somente com os IPs/CIDRs reais dos proxies confiáveis**, `real_ip_header X-Forwarded-For` e `real_ip_recursive on` no vhost. A identificação HTTPS e a confiança em cabeçalhos de protocolo também precisam corresponder à topologia do proxy; não confie em cabeçalhos externos arbitrários.
- OPcache permanece com validação de timestamps a cada 2 s para suportar atualização de arquivos/volumes. Em implantação imutável, desativar essa validação exige recriar/reiniciar o FPM em todo deploy. Monitore saturação antes de aumentar a memória do cache.

As configurações HTTP compartilhadas chegam tanto à imagem Nginx quanto às imagens PHP; o binário e os módulos personalizados continuam herdados de `jeffersonsouza/nginx`. Restrições de rede, CPU, memória, TLS do proxy e políticas da aplicação precisam ser aplicadas no ambiente de implantação.

## Teste de carga local

Os resultados registrados em 21/10/2026 estão em [docs/load-test-2026-10-21.md](docs/load-test-2026-10-21.md). A página inicial mostra um resumo recolhível, separado dos diagnósticos dinâmicos.

O script usa Python 3, Docker com cgroup v2 e ApacheBench (`ab`). Ele cria um contêiner temporário limitado a 1 CPU, sem swap, e remove esse contêiner ao terminar. Os contêineres existentes não são alterados. A saída inclui resultados brutos do `ab`, logs e `summary.json`, com erros, throughput, p95/p99, pico de memória e eventos de OOM.

```sh
python3 .github/scripts/load-test-php.py php-local:8.5 8.5 \
  --memory 256 --children 1 --output /tmp/php85-load-256
python3 .github/scripts/load-test-php.py php-local:8.5 8.5 \
  --memory 512 --children 2 --output /tmp/php85-load-512
```

Cada perfil executa cinco etapas de 10 s: index dinâmico com 1, 8 e 32 clientes; rota sintética sem cache que aloca 96 MiB, executa PBKDF2 e espera 20 ms com 32 clientes; e resposta pública em cache com 32 clientes. Os diretórios de saída devem ser novos para não misturar rodadas. Falhas HTTP/conexão, OOM ou parada inesperada fazem o teste falhar; latência é registrada sem presumir um SLO da aplicação. ApacheBench usa HTTP/1.0; `keepalive_requests` registra se houve reaproveitamento efetivo. Faça também uma verificação com o protocolo e o gerador de carga usados pela aplicação real.

Esses resultados validam a infraestrutura de demonstração. Não incluem banco de dados, Redis, TLS, rede externa ou a aplicação real e não representam capacidade garantida de produção. A carga é gerada no mesmo host do Docker.
