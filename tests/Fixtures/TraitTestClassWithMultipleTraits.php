<?php declare(strict_types=1);

namespace Tests\Fixtures;

class TraitTestClassWithMultipleTraits
{
    use TraitTestAuditableTrait;
    use TraitTestLoggableTrait;
}
