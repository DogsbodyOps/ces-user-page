<#
.SYNOPSIS
    Creates an Active Directory user account for a specified customer OU,
    adds the account to the required groups, generates a secure password,
    and outputs the result as JSON.

.DESCRIPTION
    Called by process.php.  All parameters are passed via a temporary JSON
    file to avoid any command-line injection risk.

.PARAMETER ParamsFile
    Path to the temporary JSON file containing the following keys:
      FirstName  – User's given name (required)
      LastName   – User's surname (required)
      OU         – Distinguished Name of the target OU (required)
      Groups     – Comma-separated AD group names to add the user to
      JobTitle   – Optional job title
      Department – Optional department

.OUTPUTS
    JSON object on stdout:
      { "success": true,  "sam": "...", "password": "...", "displayName": "...", "upn": "..." }
      { "success": false, "error": "..." }
#>

param(
    [Parameter(Mandatory = $true)]
    [string]$ParamsFile
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

function New-SecurePassword {
    <#
    Generates a random password of exactly $Length characters that satisfies
    complexity requirements (upper, lower, digit, special).
    Default length is 16; the maximum enforced value is 20.
    #>
    param([int]$Length = 16)

    if ($Length -gt 20) { $Length = 20 }

    $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ'   # excludes uppercase O (looks like zero)
    $lower   = 'abcdefghjkmnpqrstuvwxyz'    # excludes lowercase l (looks like one)
    $digits  = '23456789'                   # excludes 0 (zero) and 1 (one)
    $special = '!@#$%^&*'

    # Guarantee at least one of each category
    $chars = @(
        $upper[$( Get-Random -Maximum $upper.Length )],
        $lower[$( Get-Random -Maximum $lower.Length )],
        $digits[$( Get-Random -Maximum $digits.Length )],
        $special[$( Get-Random -Maximum $special.Length )]
    )

    $all = $upper + $lower + $digits + $special
    for ($i = $chars.Count; $i -lt $Length; $i++) {
        $chars += $all[$( Get-Random -Maximum $all.Length )]
    }

    # Fisher-Yates shuffle
    for ($i = $chars.Count - 1; $i -gt 0; $i--) {
        $j = Get-Random -Maximum ($i + 1)
        $tmp = $chars[$i]; $chars[$i] = $chars[$j]; $chars[$j] = $tmp
    }

    return -join $chars
}

function New-SAMAccountName {
    <#
    Builds a unique SAMAccountName: first initial + last name, lowercase,
    alphanumeric only, max 20 characters.  Appends an incrementing number
    if the base name is already taken.
    #>
    param([string]$First, [string]$Last)

    $base = ($First[0].ToString() + $Last).ToLower() -replace '[^a-z0-9]', ''
    if ($base.Length -gt 20) { $base = $base.Substring(0, 20) }

    $sam     = $base
    $counter = 1

    while ($null -ne (Get-ADUser -Filter "SamAccountName -eq '$sam'" -ErrorAction SilentlyContinue)) {
        $counter++
        $suffix = $counter.ToString()
        $trim   = [Math]::Min($base.Length, 20 - $suffix.Length)
        $sam    = $base.Substring(0, $trim) + $suffix
    }

    return $sam
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

try {
    # Read and remove the temp params file
    if (-not (Test-Path $ParamsFile)) {
        throw "Parameters file not found: $ParamsFile"
    }
    $json   = Get-Content -Path $ParamsFile -Raw -Encoding UTF8
    Remove-Item -Path $ParamsFile -Force -ErrorAction SilentlyContinue

    $p = $json | ConvertFrom-Json

    # Validate required fields
    foreach ($field in @('FirstName', 'LastName', 'OU')) {
        if ([string]::IsNullOrWhiteSpace($p.$field)) {
            throw "Missing required parameter: $field"
        }
    }

    Import-Module ActiveDirectory -ErrorAction Stop

    $sam         = New-SAMAccountName -First $p.FirstName -Last $p.LastName
    $plainPwd    = New-SecurePassword -Length 16
    $securePwd   = ConvertTo-SecureString $plainPwd -AsPlainText -Force
    $displayName = "$($p.FirstName) $($p.LastName)"
    $domain      = (Get-ADDomain).DNSRoot
    $upn         = "$sam@$domain"

    $newUserParams = @{
        SamAccountName        = $sam
        UserPrincipalName     = $upn
        Name                  = $displayName
        GivenName             = $p.FirstName
        Surname               = $p.LastName
        DisplayName           = $displayName
        AccountPassword       = $securePwd
        Enabled               = $true
        Path                  = $p.OU
        PasswordNeverExpires  = $false
        ChangePasswordAtLogon = $true
    }

    if (-not [string]::IsNullOrWhiteSpace($p.JobTitle))   { $newUserParams['Title']      = $p.JobTitle }
    if (-not [string]::IsNullOrWhiteSpace($p.Department)) { $newUserParams['Department'] = $p.Department }

    New-ADUser @newUserParams

    # Add to groups
    if (-not [string]::IsNullOrWhiteSpace($p.Groups)) {
        foreach ($group in ($p.Groups -split ',')) {
            $group = $group.Trim()
            if ($group) {
                try {
                    Add-ADGroupMember -Identity $group -Members $sam -ErrorAction Stop
                } catch {
                    # Log group-add failure but do not abort — user was created
                    Write-Warning "Could not add '$sam' to group '$group': $_"
                }
            }
        }
    }

    @{
        success     = $true
        sam         = $sam
        password    = $plainPwd
        displayName = $displayName
        upn         = $upn
    } | ConvertTo-Json -Compress

} catch {
    @{
        success = $false
        error   = $_.Exception.Message
    } | ConvertTo-Json -Compress
}
