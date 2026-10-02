param(
    [string] $MySqlDump = 'C:\xampp\mysql\bin\mysqldump.exe'
)

$ErrorActionPreference = 'Stop'

$himsRoot = Split-Path -Parent $PSScriptRoot
$himsEnvPath = Join-Path $himsRoot '.env'
$himsEnv = @{}

Get-Content -LiteralPath $himsEnvPath |
    Where-Object { $_ -match '^([A-Za-z_][A-Za-z0-9_]*)=(.*)$' } |
    ForEach-Object {
        $himsValue = $Matches[2]

        if ($himsValue.Length -ge 2 -and
            (($himsValue.StartsWith('"') -and $himsValue.EndsWith('"')) -or
             ($himsValue.StartsWith("'") -and $himsValue.EndsWith("'")))) {
            $himsValue = $himsValue.Substring(1, $himsValue.Length - 2)
        }

        $himsEnv[$Matches[1]] = $himsValue
    }

$himsRequired = 'DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_DATABASE', 'MYSQL_ATTR_SSL_CA'
$himsMissing = @($himsRequired | Where-Object { -not $himsEnv.ContainsKey($_) -or [string]::IsNullOrWhiteSpace($himsEnv[$_]) })

if ($himsMissing.Count -gt 0) {
    throw "Missing required .env settings: $($himsMissing -join ', ')"
}

if (-not (Test-Path -LiteralPath $MySqlDump -PathType Leaf)) {
    throw "mysqldump was not found at $MySqlDump"
}

$himsCaPath = $himsEnv['MYSQL_ATTR_SSL_CA']
if (-not [System.IO.Path]::IsPathRooted($himsCaPath)) {
    $himsCaPath = [System.IO.Path]::GetFullPath((Join-Path $himsRoot $himsCaPath))
}

if (-not (Test-Path -LiteralPath $himsCaPath -PathType Leaf)) {
    throw 'The configured MYSQL_ATTR_SSL_CA file does not exist.'
}

$himsBackupDir = Join-Path $himsRoot 'storage/app/backups'
[System.IO.Directory]::CreateDirectory($himsBackupDir) | Out-Null

$himsStamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$himsBackupPath = Join-Path $himsBackupDir "hims-predeploy-$himsStamp.sql"
$himsErrorPath = Join-Path ([System.IO.Path]::GetTempPath()) "hims-mysqldump-$himsStamp.err"
$himsHadPassword = Test-Path Env:MYSQL_PWD
$himsPreviousPassword = $env:MYSQL_PWD
$env:MYSQL_PWD = if ($himsEnv.ContainsKey('DB_PASSWORD')) { $himsEnv['DB_PASSWORD'] } else { '' }

try {
    $himsPreviousErrorAction = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'

    & $MySqlDump `
        --host=$($himsEnv['DB_HOST']) `
        --port=$($himsEnv['DB_PORT']) `
        --user=$($himsEnv['DB_USERNAME']) `
        --ssl-ca=$himsCaPath `
        --ssl-verify-server-cert `
        --quick `
        --skip-lock-tables `
        --skip-add-locks `
        --hex-blob `
        --default-character-set=utf8mb4 `
        --result-file=$himsBackupPath `
        $himsEnv['DB_DATABASE'] 2> $himsErrorPath

    $himsExitCode = $LASTEXITCODE
} finally {
    $ErrorActionPreference = $himsPreviousErrorAction

    if ($himsHadPassword) {
        $env:MYSQL_PWD = $himsPreviousPassword
    } else {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }
}

if ($himsExitCode -ne 0) {
    Remove-Item -LiteralPath $himsBackupPath -Force -ErrorAction SilentlyContinue
    Write-Output "backup_status=failed exit_code=$himsExitCode"
    exit $himsExitCode
}

$himsInfo = Get-Item -LiteralPath $himsBackupPath
$himsCreateCount = (Select-String -LiteralPath $himsBackupPath -Pattern '^CREATE TABLE ' | Measure-Object).Count
$himsInsertCount = (Select-String -LiteralPath $himsBackupPath -Pattern '^INSERT INTO ' | Measure-Object).Count
$himsCompleted = [bool](Select-String -LiteralPath $himsBackupPath -Pattern '^-- Dump completed on ' -Quiet)

if ($himsInfo.Length -le 0 -or $himsCreateCount -le 0 -or -not $himsCompleted) {
    Remove-Item -LiteralPath $himsBackupPath -Force -ErrorAction SilentlyContinue
    Write-Output "backup_status=invalid size=$($himsInfo.Length) create_tables=$himsCreateCount completed=$himsCompleted"
    exit 2
}

$himsHash = (Get-FileHash -LiteralPath $himsBackupPath -Algorithm SHA256).Hash
[System.IO.File]::WriteAllText(
    $himsBackupPath + '.sha256',
    "$himsHash  $($himsInfo.Name)`r`n",
    [System.Text.UTF8Encoding]::new($false)
)

Remove-Item -LiteralPath $himsErrorPath -Force -ErrorAction SilentlyContinue

Write-Output 'backup_status=verified'
Write-Output 'consistency=per-statement'
Write-Output "backup_file=$himsBackupPath"
Write-Output "size_bytes=$($himsInfo.Length) create_tables=$himsCreateCount insert_statements=$himsInsertCount sha256=$himsHash"
