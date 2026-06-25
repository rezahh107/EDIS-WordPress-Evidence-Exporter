$ErrorActionPreference = 'Stop'
$Script = Join-Path $PSScriptRoot 'run-local-validation.php'
& php $Script @args
exit $LASTEXITCODE
