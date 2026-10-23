# Publica o código em operacoes.prosperamt.com.br.
# O público vai para a raiz do subdomínio. src, views e o schema vão para a pasta pai.
# Não envia .env, fotos, banco nem viagens, e não mexe no site prosperamt.com.br.
#
# Token (uma linha, fora do Git):
#   .hostinger\token
#   %USERPROFILE%\.hostinger\token
# ou a variável HOSTINGER_API_TOKEN.
# Sem nenhum deles, o script pede o token na hora, sem mostrar o que é colado.
#
#   .\deploy.ps1 -Listar
#   .\deploy.ps1
#   .\deploy.ps1 -Sim

[CmdletBinding()]
param(
    [switch]$Listar,
    [switch]$Sim
)

$ErrorActionPreference = 'Stop'
$raiz = $PSScriptRoot
$temporaria = $null

# Pasta dos arquivos temporários do envio, num caminho sem acento. Com acento no nome do usuário
# (C:\Users\Fábio), o Windows dá à pasta TEMP um nome curto (C:\Users\FBIO~1) que o PowerShell não acha,
# e o envio parava antes de começar. Tenta C:\Users\Public, depois C:\ e, por último, a TEMP.
function Initialize-Temporaria {
    $candidatas = @()
    if ($env:PUBLIC) { $candidatas += Join-Path $env:PUBLIC 'operacoes-deploy' }
    if ($env:SystemDrive) { $candidatas += Join-Path $env:SystemDrive 'operacoes-deploy' }
    if ($env:TEMP) { $candidatas += Join-Path $env:TEMP 'operacoes-deploy' }
    foreach ($candidata in $candidatas) {
        try {
            New-Item -ItemType Directory -Force -Path $candidata -ErrorAction Stop | Out-Null
            # Confere gravando de verdade: a pasta pode existir sem deixar gravar.
            $teste = Join-Path $candidata 'teste.tmp'
            [System.IO.File]::WriteAllText($teste, 'ok')
            Remove-Item -LiteralPath $teste -ErrorAction SilentlyContinue
            return $candidata
        } catch {
        }
    }
    throw 'Não foi possível criar a pasta temporária do envio.'
}

function Remove-Temporario([string[]]$caminhos) {
    foreach ($caminho in $caminhos) {
        if ($caminho -and (Test-Path -LiteralPath $caminho)) {
            Remove-Item -LiteralPath $caminho -ErrorAction SilentlyContinue
        }
    }
}

function Read-Token {
    if ($env:HOSTINGER_API_TOKEN -and $env:HOSTINGER_API_TOKEN.Trim() -ne '') {
        return $env:HOSTINGER_API_TOKEN.Trim()
    }
    $candidatos = @(
        (Join-Path $raiz '.hostinger\token'),
        (Join-Path $env:USERPROFILE '.hostinger\token')
    )
    foreach ($arquivo in $candidatos) {
        if (Test-Path -LiteralPath $arquivo) {
            $texto = (Get-Content -Raw -LiteralPath $arquivo).Trim()
            if ($texto -ne '') {
                return $texto
            }
        }
    }
    if (-not $Sim) {
        # Pede na hora. O que é colado não aparece na tela, nem em print.
        $seguro = Read-Host 'Cole o token da API Hostinger e aperte Enter (ele não aparece na tela)' -AsSecureString
        $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($seguro)
        try {
            $texto = ([Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) + '').Trim()
        } finally {
            [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
        }
        if ($texto -ne '') {
            return $texto
        }
    }
    throw "Falta o token da API Hostinger. Salve uma linha em .hostinger\token ou defina HOSTINGER_API_TOKEN."
}

function Read-Site {
    $arquivo = Join-Path $raiz '.hostinger\site.json'
    if (-not (Test-Path -LiteralPath $arquivo)) {
        throw "Falta $arquivo"
    }
    $site = Get-Content -Raw -LiteralPath $arquivo | ConvertFrom-Json
    foreach ($campo in @('domain', 'username', 'parentDomain', 'appDirectory')) {
        if (-not $site.$campo) {
            throw "O arquivo .hostinger\site.json precisa do campo $campo."
        }
    }
    return $site
}

function Test-DestinoSeguro([string]$destino) {
    $normal = ($destino -replace '\\', '/').Trim('/')
    if ($normal -match '(^|/)\.\.(/|$)') { return $false }
    if ($normal -match '(^|/)\.env($|\.)') { return $false }
    if ($normal -match '(^|/)storage(/|$)') { return $false }
    if ($normal -eq 'operacoes/.htaccess' -or $normal -eq 'operacoes/.env') { return $false }
    return $true
}

# $primeiros e $ultimos (caminhos relativos à pasta) mudam a ordem de envio dentro da pasta.
# $fora (caminhos relativos) ficam de fora: são enviados em outro ponto da ordem.
function Add-Arquivos([System.Collections.Generic.List[object]]$lista, [string]$pasta, [string]$credencial, [string]$prefixo, [string[]]$primeiros = @(), [string[]]$ultimos = @(), [string[]]$fora = @()) {
    if (-not (Test-Path -LiteralPath $pasta)) {
        return
    }
    $base = (Resolve-Path -LiteralPath $pasta).Path
    $inicio = New-Object 'System.Collections.Generic.List[object]'
    $meio = New-Object 'System.Collections.Generic.List[object]'
    $fim = New-Object 'System.Collections.Generic.List[object]'
    Get-ChildItem -LiteralPath $base -Recurse -File | ForEach-Object {
        $nome = $_.Name
        if ($nome -eq 'router.php' -or $nome -eq 'Thumbs.db' -or $nome -eq '.DS_Store') {
            return
        }
        $relativo = $_.FullName.Substring($base.Length).TrimStart('\', '/') -replace '\\', '/'
        if ($fora -contains $relativo) {
            return
        }
        $destino = if ($prefixo) { ($prefixo.Trim('/') + '/' + $relativo) } else { $relativo }
        if (-not (Test-DestinoSeguro $destino)) {
            throw "Destino recusado: $destino"
        }
        $item = [pscustomobject]@{
            Credencial = $credencial
            Destino    = $destino
            Arquivo    = $_.FullName
        }
        if ($primeiros -contains $relativo) {
            $inicio.Add($item)
        } elseif ($ultimos -contains $relativo) {
            $fim.Add($item)
        } else {
            $meio.Add($item)
        }
    }
    foreach ($nome in $primeiros) {
        foreach ($item in $inicio) {
            if ($item.Destino.EndsWith('/' + $nome) -or $item.Destino -eq $nome) { $lista.Add($item) }
        }
    }
    foreach ($item in $meio) { $lista.Add($item) }
    foreach ($nome in $ultimos) {
        foreach ($item in $fim) {
            if ($item.Destino.EndsWith('/' + $nome) -or $item.Destino -eq $nome) { $lista.Add($item) }
        }
    }
}

function Get-StatusHttp([string]$cabecalho) {
    if (-not (Test-Path -LiteralPath $cabecalho)) {
        return 0
    }
    $http = @(Get-Content -LiteralPath $cabecalho | Where-Object { $_ -match '^HTTP/' })
    if ($http.Count -eq 0) {
        return 0
    }
    $partes = $http[-1] -split '\s+'
    if ($partes.Count -lt 2) {
        return 0
    }
    return [int]$partes[1]
}

function Join-TusUrl([string]$base, [string]$destino) {
    $url = $base.TrimEnd('/')
    foreach ($parte in ($destino -split '/')) {
        if ($parte -ne '') {
            $url += '/' + [uri]::EscapeDataString($parte)
        }
    }
    return $url
}

function Invoke-Curl {
    param([string[]]$Argumentos)
    & curl.exe --ssl-no-revoke @Argumentos
    if ($LASTEXITCODE -ne 0) {
        throw "O curl terminou com o código $LASTEXITCODE."
    }
}

function Get-CredencialEnvio([string]$token, [string]$username, [string]$domain) {
    $corpo = Join-Path $temporaria ("hostinger-pedido-" + [guid]::NewGuid().ToString() + '.json')
    $cabecalho = Join-Path $temporaria ("hostinger-cab-" + [guid]::NewGuid().ToString() + '.txt')
    $resposta = Join-Path $temporaria ("hostinger-resp-" + [guid]::NewGuid().ToString() + '.json')
    try {
        $json = (@{ username = $username; domain = $domain } | ConvertTo-Json -Compress)
        [System.IO.File]::WriteAllText($corpo, $json)
        Invoke-Curl -Argumentos @(
            '--ipv4', '-sS', '-D', $cabecalho, '-o', $resposta,
            '-X', 'POST', 'https://developers.hostinger.com/api/hosting/v1/files/upload-urls',
            '-H', "Authorization: Bearer $token",
            '-H', 'Content-Type: application/json',
            '-H', 'Accept: application/json',
            '--data-binary', "@$corpo"
        )
        $status = Get-StatusHttp $cabecalho
        if ($status -ne 200) {
            throw "A Hostinger recusou o endereço de envio de $domain (HTTP $status)."
        }
        $dados = Get-Content -Raw -LiteralPath $resposta | ConvertFrom-Json
        if (-not $dados.url -or -not $dados.auth_key -or -not $dados.rest_auth_key) {
            throw "A Hostinger não devolveu o endereço de envio de $domain."
        }
        return $dados
    } finally {
        Remove-Temporario @($corpo, $cabecalho, $resposta)
    }
}

function Send-Arquivo($credencial, [string]$destino, [string]$arquivo) {
    $tamanho = (Get-Item -LiteralPath $arquivo).Length
    $url = (Join-TusUrl $credencial.url $destino) + '?override=true'
    $cabecalho = Join-Path $temporaria ("hostinger-tus-" + [guid]::NewGuid().ToString() + '.txt')
    $copia = $null
    try {
        # Projeto numa pasta com acento: o curl recebe uma cópia do arquivo num caminho sem acento.
        $origem = $arquivo
        if ($arquivo -match '[^\x20-\x7E]') {
            $copia = Join-Path $temporaria ("hostinger-envio-" + [guid]::NewGuid().ToString() + '.bin')
            Copy-Item -LiteralPath $arquivo -Destination $copia
            $origem = $copia
        }
        $comum = @(
            '--ipv4', '-sS', '-D', $cabecalho, '-o', 'NUL',
            '-H', "X-Auth: $($credencial.auth_key)",
            '-H', "X-Auth-Rest: $($credencial.rest_auth_key)",
            '-H', 'Tus-Resumable: 1.0.0'
        )
        Invoke-Curl -Argumentos ($comum + @('-X', 'POST', $url, '-H', "Upload-Length: $tamanho", '-H', 'Upload-Offset: 0'))
        $status = Get-StatusHttp $cabecalho
        if ($status -ne 201 -and $status -ne 200 -and $status -ne 204) {
            throw "Falha ao abrir o envio de $destino (HTTP $status)."
        }
        if ($tamanho -gt 0) {
            Invoke-Curl -Argumentos ($comum + @(
                '-X', 'PATCH', $url,
                '-H', 'Content-Type: application/offset+octet-stream',
                '-H', 'Upload-Offset: 0',
                '--data-binary', "@$origem"
            ))
            $status = Get-StatusHttp $cabecalho
            if ($status -ne 204 -and $status -ne 200) {
                throw "Falha ao enviar $destino (HTTP $status)."
            }
        }
    } finally {
        Remove-Temporario @($cabecalho, $copia)
    }
}

function Clear-CacheSite([string]$token, [string]$username, [string]$domain) {
    $cabecalho = Join-Path $temporaria ("hostinger-cache-" + [guid]::NewGuid().ToString() + '.txt')
    try {
        $endereco = "https://developers.hostinger.com/api/hosting/v1/accounts/$username/websites/$domain/cache/clear"
        Invoke-Curl -Argumentos @(
            '--ipv4', '-sS', '-D', $cabecalho, '-o', 'NUL',
            '-X', 'DELETE', $endereco,
            '-H', "Authorization: Bearer $token",
            '-H', 'Accept: application/json'
        )
        $status = Get-StatusHttp $cabecalho
        if ($status -ne 200) {
            Write-Host "O cache de $domain não foi limpo (HTTP $status). Os arquivos já foram enviados."
        }
    } finally {
        Remove-Temporario @($cabecalho)
    }
}

function Get-CodigoHttp([string]$url) {
    $codigo = & curl.exe --ssl-no-revoke --ipv4 -sS -o NUL -w '%{http_code}' --max-time 40 $url
    if ($LASTEXITCODE -ne 0) {
        return 0
    }
    return [int]$codigo
}

$site = Read-Site
$lista = New-Object 'System.Collections.Generic.List[object]'
# Ordem de envio: o site continua no ar enquanto os arquivos sobem.
# 1. O schema primeiro: o código antigo ignora as tabelas novas.
# 2. As classes novas e o bootstrap.php, que só passa a carregá-las.
# 3. As telas: as novas funcionam com o código antigo. Tela que usa função nova do index.php ou do helpers.php
#    (enviados por último) confere antes com function_exists.
# 4. O resto do código, começando pelo Db.php (ele atualiza o banco), o Lavra.php logo depois (grava a coluna nova
#    das toneladas) e o Auth.php (o Pessoas.php novo usa função nova dele).
# 5. Os arquivos públicos e, por fim, helpers.php e index.php um logo depois do outro:
#    o index.php novo usa funções novas do helpers.php, e o antigo usa funções que o novo mantém.
$schema = Join-Path $raiz 'database\schema.sql'
if (-not (Test-Path -LiteralPath $schema)) {
    throw 'Falta database\schema.sql'
}
$destinoSchema = $site.appDirectory.Trim('/') + '/database/schema.sql'
if (-not (Test-DestinoSeguro $destinoSchema)) {
    throw "Destino recusado: $destinoSchema"
}
$lista.Add([pscustomobject]@{
    Credencial = 'pai'
    Destino    = $destinoSchema
    Arquivo    = $schema
})
# O Posse.php vai depois do bootstrap.php: ele usa a classe Oficina, que só o bootstrap novo carrega.
$classesNovas = @('Fotos.php', 'NotaFiscal.php', 'Problemas.php', 'Pecas.php', 'Oficina.php', 'FinPro.php', 'bootstrap.php', 'Posse.php')
foreach ($nome in $classesNovas) {
    $lista.Add([pscustomobject]@{
        Credencial = 'pai'
        Destino    = $site.appDirectory.Trim('/') + '/src/' + $nome
        Arquivo    = Join-Path (Join-Path $raiz 'src') $nome
    })
}
Add-Arquivos $lista (Join-Path $raiz 'views') 'pai' ($site.appDirectory + '/views') @('trecho_operador.php', 'tablets.php')
Add-Arquivos $lista (Join-Path $raiz 'src') 'pai' ($site.appDirectory + '/src') @('Db.php', 'Lavra.php', 'Tablets.php', 'Auth.php') @() ($classesNovas + @('helpers.php'))
Add-Arquivos $lista (Join-Path $raiz 'public') 'subdominio' '' @() @() @('index.php')
$lista.Add([pscustomobject]@{
    Credencial = 'pai'
    Destino    = $site.appDirectory.Trim('/') + '/src/helpers.php'
    Arquivo    = Join-Path (Join-Path $raiz 'src') 'helpers.php'
})
$lista.Add([pscustomobject]@{
    Credencial = 'subdominio'
    Destino    = 'index.php'
    Arquivo    = Join-Path (Join-Path $raiz 'public') 'index.php'
})

Write-Host ("{0} arquivos para {1}" -f $lista.Count, $site.domain)
if ($Listar) {
    $lista | ForEach-Object { Write-Host ("  {0}" -f $_.Destino) }
    exit 0
}

if (-not $Sim) {
    $resposta = Read-Host 'Enter publica. Digite nao para cancelar'
    if ($resposta -match '^(n|nao|não)$') {
        Write-Host 'Publicação cancelada.'
        exit 0
    }
}

$token = Read-Token
$temporaria = Initialize-Temporaria
Write-Host 'Pedindo os endereços de envio...'
$credenciais = @{
    subdominio = Get-CredencialEnvio $token $site.username $site.domain
    pai        = Get-CredencialEnvio $token $site.username $site.parentDomain
}

$enviados = 0
foreach ($item in $lista) {
    Write-Host ("  {0}" -f $item.Destino)
    Send-Arquivo $credenciais[$item.Credencial] $item.Destino $item.Arquivo
    $enviados++
}

Write-Host 'Limpando o cache do subdomínio...'
Clear-CacheSite $token $site.username $site.domain

$saude = 0
$hashIgual = $false
$fluxoRemoto = Join-Path $temporaria 'operacoes-fluxo-remoto.js'
$fluxoLocal = Join-Path $raiz 'public\assets\fluxo.js'
for ($tentativa = 1; $tentativa -le 4; $tentativa++) {
    $saude = Get-CodigoHttp ("https://{0}/health" -f $site.domain)
    if (Test-Path -LiteralPath $fluxoLocal) {
        & curl.exe --ssl-no-revoke --ipv4 -sS -o $fluxoRemoto --max-time 40 ("https://{0}/assets/fluxo.js" -f $site.domain)
        if ($LASTEXITCODE -eq 0 -and (Test-Path -LiteralPath $fluxoRemoto)) {
            $hashIgual = (Get-FileHash -LiteralPath $fluxoRemoto -Algorithm SHA256).Hash -eq (Get-FileHash -LiteralPath $fluxoLocal -Algorithm SHA256).Hash
        }
    }
    if ($saude -eq 200 -and $hashIgual) { break }
    Start-Sleep -Seconds 3
}
Remove-Temporario @($fluxoRemoto)
$bloqueio = 0
for ($tentativa = 1; $tentativa -le 3; $tentativa++) {
    $bloqueio = Get-CodigoHttp ("https://{0}/{1}/.env" -f $site.parentDomain, $site.appDirectory)
    if ($bloqueio -eq 403) { break }
    Start-Sleep -Seconds 2
}
# As sessões ficam em storage/sessoes. A pasta precisa estar fechada para a web.
$sessoes = 0
for ($tentativa = 1; $tentativa -le 3; $tentativa++) {
    $sessoes = Get-CodigoHttp ("https://{0}/{1}/storage/sessoes/.htaccess" -f $site.parentDomain, $site.appDirectory)
    if ($sessoes -eq 403) { break }
    Start-Sleep -Seconds 2
}

Write-Host ("Enviados: {0}" -f $enviados)
Write-Host ("Saúde do site: HTTP {0}" -f $saude)
Write-Host ("Arquivo .env no domínio principal: HTTP {0}" -f $bloqueio)
Write-Host ("Pasta de sessões no domínio principal: HTTP {0}" -f $sessoes)
if ($hashIgual) {
    Write-Host 'O fluxo.js publicado é o mesmo deste computador.'
} else {
    Write-Host 'O fluxo.js publicado ainda não bate com o deste computador.'
}

if ($saude -ne 200 -or $bloqueio -ne 403 -or $sessoes -ne 403 -or -not $hashIgual) {
    exit 1
}
