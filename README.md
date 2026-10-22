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

## Instalação por APK — 22/10/2026

| Série | Base Nginx personalizada | Origem do PHP e extensões |
| --- | --- | --- |
| 8.2 | Alpine 3.23 | Runtime oficial `php:8.2.35-fpm-alpine3.23`; extensões adicionais compiladas e bibliotecas nativas via APK |
| 8.3 | Alpine 3.24 | Pacotes `php83-*` do Alpine, com mínimo 8.3.36 |
| 8.4 | Alpine 3.24 | Pacotes `php84-*` do Alpine, com mínimo 8.4.27 |
| 8.5 | Alpine 3.24 | Pacotes `php85-*` do Alpine, com mínimo 8.5.12 |

PHP 8.3–8.5 voltaram a usar pacotes prontos: seus Dockerfiles não executam `install-php-extensions`. Os índices oficiais Alpine 3.24 consultados em 22/10/2026 ofereciam os três patches corrigidos e Redis/Brotli/Imagick correspondentes em AMD64 e ARM64. Os módulos padrão `posix`, `sqlite3` e `xmlreader` também são mantidos por APK. A imagem Nginx continua sendo a personalizada deste repositório, com seus módulos, regras de bots e arquivos. O build compara os hashes do binário Nginx e de todos os módulos antes e depois de instalar PHP, falhando se algum deles for substituído.

O PHP 8.2 ainda não está no Alpine 3.24, e o pacote da 3.22 continua em 8.2.33. Ele usa o núcleo já compilado da imagem oficial; não recompilamos o núcleo PHP. Apenas as extensões adicionais são compiladas para esse runtime. Bibliotecas nativas, como ICU, libzip e OpenSSL, são instaladas por APK. Misturar extensões PHP 8.2.33 da distribuição com o núcleo 8.2.35 não garante a mesma versão de código nem compatibilidade de compilação; não fazemos essa combinação. O instalador de extensões permanece fixado por digest.

Os pacotes PHP pertencem ao repositório `community`, cujo suporte regular dura até a próxima versão estável do Alpine. Acompanhe a [política de suporte](https://alpinelinux.org/releases/) e atualize a base personalizada junto com a versão Alpine; `apk upgrade` em uma base antiga não assegura PHP atualizado.

Todos os builds e testes conferem a série e a versão mínima corrigida. Para atualizações futuras, altere o mínimo no Dockerfile e em `.github/scripts/smoke-test-php.sh`; no 8.2, atualize também a tag oficial do runtime.

## Executar pipelines manualmente

Todos os workflows têm `workflow_dispatch`. Após incorporar os arquivos na branch padrão, abra **Actions → workflow → Run workflow**. [O GitHub exige o workflow na branch padrão para habilitar esse evento](https://docs.github.com/en/actions/using-workflows/events-that-trigger-workflows#workflow_dispatch).

Nos sete workflows de imagens, a opção `publish` permite testar sem publicar. A publicação exige `publish=true` e execução em `master` ou em uma tag `v*.*.*`. PRs e `develop` apenas validam e não sobrescrevem as tags estáveis. Os secrets continuam sendo `DOCKER_USER` e `DOCKER_PASSWORD`.

Para reconstruir e publicar as quatro séries com GitHub CLI:

```sh
for version in 82 83 84 85; do
  gh workflow run "docker-publish-php${version}.yml" --ref master -f publish=true
done
```

O workflow **NGINX** agora também publica `jeffersonsouza/nginx:alpine-3.24`. Os workflows PHP constroem a base Nginx a partir do mesmo commit e a passam por um contexto OCI local; assim a validação da PR não depende de a nova tag já existir no Docker Hub nem da ordem das publicações. Fora do CI, os Dockerfiles usam as tags publicadas por padrão ou aceitam `--build-arg NGINX_IMAGE=...` para uma base local personalizada.

As pipelines PHP 8.2–8.5 usam dois jobs paralelos em runners nativos: `ubuntu-24.04` para AMD64 e `ubuntu-24.04-arm` para ARM64. Não usam QEMU. Cada arquitetura passa pelos testes de 256 e 512 MiB antes de enviar uma imagem sem tag estável ao registry. A configuração publicada é comparada à imagem testada; a tag multi-arquitetura só é criada após o sucesso dos dois jobs, usando seus digests.

PHP 8.3–8.5 instalam APKs sem cache em cada execução, buscando os pacotes atuais. PHP 8.2 mantém cache BuildKit/GitHub Actions das extensões, separado por arquitetura, mas reconstrói o estágio final `runtime` e atualiza suas dependências APK em toda execução. Mudanças na imagem oficial, no instalador ou na lista de extensões invalidam a camada de compilação. A primeira execução ainda compila essas extensões, em hardware nativo; as seguintes podem reutilizá-las. Os testes cobrem versão, origem APK, extensões, caminhos compatíveis, configurações, Nginx → FPM, página inicial sem `phpinfo()`, respostas privadas, cookies, autenticação e desligamento do contêiner.

Os filtros de caminhos evitam builds sem alterações relevantes. As actions são fixadas por SHA e as permissões dos builds se limitam à leitura do repositório.

O workflow `Issue closer` também pode ser executado manualmente, informando `issue_number`. Ele mantém a regra existente de fechar issues cujo corpo não contenha `guidelines for Contributing`, usando a action oficial `github-script`. Essa regra foi herdada de outro projeto e merece revisão antes de continuar sendo usada.

## Build e teste local

O contexto dos Dockerfiles é **`new/`**; os arquivos de configuração PHP são referenciados a partir desse diretório:

```sh
docker build --pull --build-arg BASE_TAG=3.24 \
  --tag nginx-local:3.24 ./new/nginx
docker build --no-cache --build-arg NGINX_IMAGE=nginx-local:3.24 \
  --file new/8.5-fpm/Dockerfile --tag php-local:8.5 ./new
bash .github/scripts/smoke-test-php.sh php-local:8.5 8.5
```

Esses comandos usam o builder Docker local. Troque a série no caminho, na tag e no teste para validar 8.3 ou 8.4. Para 8.2, use a base Nginx 3.23 e `--no-cache-filter runtime` no build PHP para preservar a compilação das extensões. Builders isolados `docker-container` precisam do contexto OCI usado nos workflows, pois não enxergam as tags locais do daemon. Para testar outra arquitetura, acrescente `--platform linux/amd64` ou `--platform linux/arm64` aos dois builds; prefira executar na arquitetura nativa.

## Compatibilidade e implantação

- A raiz `/` das imagens PHP 8.2–8.5 executa a página de boas-vindas compartilhada de `new/php/`, com imagem local, dados do ambiente e horário gerado pelo PHP em cada requisição. Ela verifica FPM e extensões essenciais, devolve HTTP 200 quando as verificações passam ou 503 quando falham, e usa `Cache-Control: no-store`. Clientes que enviam `Accept: application/json` recebem o diagnóstico em JSON.
- A página HTML inclui seções recolhíveis em inglês com configurações PHP ativas, orientações básicas e uma referência histórica do teste de carga em `new/php/benchmark.json`. Esses resultados não são um teste ao vivo; a resposta JSON continua restrita ao diagnóstico dinâmico.
- O diagnóstico informa versão PHP, SAPI, família do sistema, arquitetura, timezone e nomes das extensões carregadas. Não exibe hostname, IPs, caminhos, credenciais, variáveis de ambiente, cookies, cabeçalhos de requisição nem `phpinfo()`. Versão e módulos continuam sendo informações públicas; substitua o index pelo da aplicação ou restrinja o diagnóstico quando esses metadados não devam ser publicados.
- As tags, o diretório `/var/www/html` e o UID 1000 de `www-data` são preservados.
- Os caminhos comuns `/usr/local/bin/php`, `/usr/local/sbin/php-fpm`, `/usr/local/etc/php/conf.d/` e `/usr/local/etc/php-fpm.d/` são preservados. No 8.2, pertencem ao runtime oficial. Nas imagens APK, são links para os binários e configurações nativos, como `/usr/bin/php83`, `/usr/sbin/php-fpm83` e `/etc/php83/`. Para montar diretórios inteiros de configuração nas imagens APK, prefira os caminhos nativos da série.
- PHP 8.2 usa `php.ini-production`; PHP 8.3–8.5 usam o INI da distribuição, com os mesmos ajustes compartilhados de segurança, memória e OPcache deste repositório. Instale extensões APK apenas da série correspondente nas imagens APK. Não instale `php82-*` na imagem 8.2 oficial: isso introduziria outro runtime e componentes antigos.
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

Os resultados após a migração para APK, registrados em 22/10/2026, estão em [docs/load-test-2026-10-22.md](docs/load-test-2026-10-22.md). O [relatório de 21/10](docs/load-test-2026-10-21.md) fica como histórico do runtime anterior e da correção de cache lock. A página inicial mostra o resumo atual recolhível, separado dos diagnósticos dinâmicos.

O script usa Python 3, Docker com cgroup v2 e ApacheBench (`ab`). Ele cria um contêiner temporário limitado a 1 CPU, sem swap, e remove esse contêiner ao terminar. Os contêineres existentes não são alterados. A saída inclui resultados brutos do `ab`, logs e `summary.json`, com erros, throughput, p95/p99, pico de memória e eventos de OOM.

```sh
python3 .github/scripts/load-test-php.py php-local:8.5 8.5 \
  --memory 256 --children 1 --output /tmp/php85-load-256
python3 .github/scripts/load-test-php.py php-local:8.5 8.5 \
  --memory 512 --children 2 --output /tmp/php85-load-512
```

Cada perfil executa cinco etapas de 10 s: index dinâmico com 1, 8 e 32 clientes; rota sintética sem cache que aloca 96 MiB, executa PBKDF2 e espera 20 ms com 32 clientes; e resposta pública em cache com 32 clientes. Os diretórios de saída devem ser novos para não misturar rodadas. Falhas HTTP/conexão, OOM ou parada inesperada fazem o teste falhar; latência é registrada sem presumir um SLO da aplicação. ApacheBench usa HTTP/1.0; `keepalive_requests` registra se houve reaproveitamento efetivo. Faça também uma verificação com o protocolo e o gerador de carga usados pela aplicação real.

Esses resultados validam a infraestrutura de demonstração. Não incluem banco de dados, Redis, TLS, rede externa ou a aplicação real e não representam capacidade garantida de produção. A carga é gerada no mesmo host do Docker.
