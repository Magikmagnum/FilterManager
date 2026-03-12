<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle;

use EricGansa\FilterManagerBundle\DependencyInjection\FilterManagerExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * FilterManagerBundle — Symfony bundle for dynamic filtering, pagination
 * and user-scoped queries with Doctrine ORM.
 *
 * @author Eric Gansa <ericgansa02@gmail.com>
 */
class FilterManagerBundle extends Bundle
{
    public function getContainerExtension(): FilterManagerExtension
    {
        return new FilterManagerExtension();
    }
}
