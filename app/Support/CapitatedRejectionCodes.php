<?php

namespace App\Support;

final class CapitatedRejectionCodes
{
    // Errores de archivo / plan
    public const PLAN_INVALID_PRODUCT          = 'PLAN_INVALID_PRODUCT';
    public const PLAN_NO_ACTIVE_VERSION        = 'PLAN_NO_ACTIVE_VERSION';
    public const PLAN_STRUCTURE_INVALID        = 'PLAN_STRUCTURE_INVALID';

    // Errores por persona
    public const PERSON_SEX_INVALID            = 'PERSON_SEX_INVALID';
    public const PERSON_AGE_INVALID            = 'PERSON_AGE_INVALID';

    public const PERSON_COUNTRY_CODE_NOT_FOUND = 'PERSON_COUNTRY_CODE_NOT_FOUND';
    public const PERSON_RESIDENCE_NOT_ALLOWED  = 'PERSON_RESIDENCE_NOT_ALLOWED';
    public const PERSON_REPATRIATION_NOT_ALLOWED = 'PERSON_REPATRIATION_NOT_ALLOWED';

    public const PERSON_INCONGRUENCE           = 'PERSON_INCONGRUENCE';
    public const PERSON_DUPLICATED             = 'PERSON_DUPLICATED';

    public const CONTINUITY_BREAK              = 'CONTINUITY_BREAK';
    public const RETROACTIVE_NOT_ALLOWED       = 'RETROACTIVE_NOT_ALLOWED';

    // Catch-all
    public const UNKNOWN_ERROR                 = 'UNKNOWN_ERROR';

    private function __construct()
    {
        // no instancias
    }
}
