# Fail CI/release if paxdesign-toolbar references reappear in this repository.
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

$patterns = @(
  'paxdesign-toolbar',
  'paxdesign_toolbar',
  'PDXDock',
  'pdx-dock',
  '#pdx-root',
  'Black10998/paxdesign-toolbar'
)

$excludeGlobs = @(
  '.gitignore',
  'production-plugin/**',
  'docs/**',
  'scripts/verify-no-toolbar.*',
  'scripts/wp-uninstall-toolbar.php',
  'scripts/DOCK-*',
  'scripts/IOS-*',
  'paxdesign-booking/scripts/wp-uninstall-toolbar.php',
  '.cursor/rules/no-paxdesign-toolbar.mdc',
  '.github/workflows/**',
  '_preserve/**',
  '_prod-check/**',
  'baseline-prod/**'
)

$fail = $false
foreach ($pattern in $patterns) {
  $hits = Get-ChildItem -Recurse -File -ErrorAction SilentlyContinue |
    Where-Object {
      $rel = $_.FullName.Substring($root.Length + 1).Replace('\', '/')
      -not ($excludeGlobs | Where-Object { $rel -like $_ })
    } |
    Select-String -Pattern $pattern -SimpleMatch -ErrorAction SilentlyContinue
  if ($hits) {
    Write-Error "Forbidden toolbar reference ($pattern):`n$($hits | Out-String)"
    $fail = $true
  }
}

if ($fail) {
  throw 'Toolbar guard failed. paxdesign-toolbar must not exist in this repository.'
}

Write-Output 'OK: no paxdesign-toolbar references in repository.'
