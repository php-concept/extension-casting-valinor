<?php declare(strict_types=1);

namespace Concept\Extensions\CastingValinor\Routing;

use Concept\Core\Http\Contracts\ArgumentResolverInterface;
use Concept\Extensions\CastingValinor\Contracts\CasterInterface;
use Concept\Extensions\CastingValinor\Exceptions\CastingException;
use Closure;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

final class TypedRouteParameterArgumentResolver implements ArgumentResolverInterface
{
    private const string ERR_CASTER_NOT_REGISTERED = 'CasterInterface factory did not return a CasterInterface instance.';

    private ?CasterInterface $caster = null;

    /** @param Closure(): CasterInterface $casterFactory */
    public function __construct(
        private readonly Closure $casterFactory,
    ) {}

    public function supports(ReflectionParameter $parameter, array $vars): bool
    {
        if (!array_key_exists($parameter->getName(), $vars)) {
            return false;
        }

        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        if ($type->isBuiltin()) {
            return true;
        }

        $typeName = $type->getName();

        return $typeName !== ServerRequestInterface::class
            && !is_subclass_of($typeName, ServerRequestInterface::class);
    }

    public function resolve(ReflectionParameter $parameter, ServerRequestInterface $request, array $vars): mixed
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType) {
            throw new CastingException('mixed');
        }

        return $this->caster()->cast($vars[$parameter->getName()], $type->getName());
    }

    private function caster(): CasterInterface
    {
        if ($this->caster === null) {
            $casterFactory = $this->casterFactory;
            $caster = $casterFactory();
            if (!$caster instanceof CasterInterface) {
                throw new RuntimeException(self::ERR_CASTER_NOT_REGISTERED);
            }

            $this->caster = $caster;
        }

        return $this->caster;
    }
}
