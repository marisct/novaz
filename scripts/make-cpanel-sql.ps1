$ErrorActionPreference = 'Stop'

$root = Resolve-Path (Join-Path $PSScriptRoot '..')
$sqlite = Join-Path $root 'database\database.sqlite'
$outDir = Join-Path $root 'build'
$out = Join-Path $outDir 'nonax-cpanel-database.sql'
$sqliteExe = 'C:\Users\hp\AppData\Local\Android\Sdk\platform-tools\sqlite3.exe'

if (-not (Test-Path $sqlite)) {
    throw "SQLite database not found: $sqlite"
}

New-Item -ItemType Directory -Force -Path $outDir | Out-Null

$dump = & $sqliteExe $sqlite '.dump'
$tableNames = foreach ($rawLine in $dump) {
    $candidate = [string]$rawLine
    if ($candidate -match '^CREATE TABLE IF NOT EXISTS "([^"]+)"' -and $matches[1] -notmatch '^sqlite_') {
        $matches[1]
    }
}

$lines = New-Object System.Collections.Generic.List[string]
$lines.Add('-- NovaX IMS MySQL import for cPanel/phpMyAdmin')
$lines.Add('-- Target database: cpfr_42287767_novax')
$lines.Add('SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";')
$lines.Add('SET time_zone = "+00:00";')
$lines.Add('SET FOREIGN_KEY_CHECKS = 0;')
$lines.Add('START TRANSACTION;')
foreach ($tableName in [array]::AsReadOnly($tableNames)) {
    $lines.Add("DROP TABLE IF EXISTS ``$tableName``;")
}

$skip = $false
$indexCounter = 1
foreach ($rawLine in $dump) {
    $line = [string]$rawLine

    if ($line -match '^(PRAGMA|BEGIN TRANSACTION|COMMIT;)') { continue }
    if ($line -match 'sqlite_sequence') { continue }

    if ($line -match '^CREATE TABLE') {
        $skip = $false
    }

    if ($line -match '^CREATE TABLE.*"sqlite_') {
        $skip = $true
        continue
    }

    if ($skip) {
        if ($line -match '^\);') { $skip = $false }
        continue
    }

    $line = $line -replace '"([^"]+)"', '`$1`'
    $line = $line -replace '\binteger primary key autoincrement not null\b', 'bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY'
    $line = $line -replace '\binteger primary key autoincrement\b', 'bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY'
    $line = $line -replace '\binteger\b', 'int'
    $line = $line -replace '\bvarchar\b', 'varchar(255)'
    $line = $line -replace '\bnumeric\b', 'decimal(10,2)'
    $line = $line -replace 'tinyint\(1\)', 'tinyint(1)'
    $line = $line -replace ' default \(''([^'']*)''\)', ' DEFAULT ''$1'''
    $line = $line -replace ' default ''([^'']*)''', ' DEFAULT ''$1'''
    $line = $line -replace ' default CURRENT_TIMESTAMP', ' DEFAULT CURRENT_TIMESTAMP'
    $line = $line -replace ' on "([^"]+)"', ' on `$1`'
    $line = $line -replace '\bCREATE INDEX\b', 'CREATE INDEX'
    $line = $line -replace '\bCREATE UNIQUE INDEX\b', 'CREATE UNIQUE INDEX'

    # Keep imports forgiving on free/shared MySQL hosts. Laravel still enforces the relationships in app code.
    # SQLite emits foreign keys inline in each CREATE TABLE statement. Remove the full clause, including actions.
    while ($line -match ', foreign key\([^)]+\) references ') {
        $line = $line -replace ', foreign key\([^)]+\) references .*?(?=, foreign key|\);)', ''
    }
    $line = $line -replace ',\s*\);$', ');'

    if ($line -match '^INSERT INTO ([A-Za-z0-9_]+) VALUES') {
        $line = $line -replace '^INSERT INTO ([A-Za-z0-9_]+) VALUES', 'INSERT INTO `$1` VALUES'
    }

    if ($line -match '^CREATE (UNIQUE )?INDEX `([^`]+)`') {
        $indexName = $matches[2]
        if ($indexName.Length -gt 60) {
            $shortName = 'idx_{0:d3}' -f $indexCounter
            $oldIndexName = '`' + $indexName + '`'
            $newIndexName = '`' + $shortName + '`'
            $line = $line -replace [regex]::Escape($oldIndexName), $newIndexName
            $indexCounter++
        }
    }

    $lines.Add($line)
}

$lines.Add('COMMIT;')
$lines.Add('SET FOREIGN_KEY_CHECKS = 1;')

[System.IO.File]::WriteAllLines($out, $lines, [System.Text.UTF8Encoding]::new($false))
Write-Host $out
