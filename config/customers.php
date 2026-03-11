<?php
/**
 * Customer configuration.
 *
 * Each key is an internal customer identifier.  Populate 'ou' with the full
 * Distinguished Name of the Organisational Unit where users should be created,
 * and list any AD group names the new user should automatically be added to in
 * the 'groups' array.
 */

function get_customers(): array
{
    return [
        'customer_alpha' => [
            'name'   => 'Alpha Corporation',
            'ou'     => 'OU=Users,OU=AlphaCorp,DC=example,DC=local',
            'groups' => [
                'AlphaCorp-Users',
                'AlphaCorp-Email',
                'VPN-Users',
            ],
        ],
        'customer_beta' => [
            'name'   => 'Beta Industries Ltd',
            'ou'     => 'OU=Users,OU=BetaInd,DC=example,DC=local',
            'groups' => [
                'BetaInd-Users',
                'BetaInd-Email',
            ],
        ],
        'customer_gamma' => [
            'name'   => 'Gamma Services',
            'ou'     => 'OU=Users,OU=GammaSvc,DC=example,DC=local',
            'groups' => [
                'GammaSvc-Users',
                'GammaSvc-Email',
                'GammaSvc-SharePoint',
            ],
        ],
    ];
}
