<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Container;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Contenedor DI minimalista con autowiring por reflexión.
 *
 * Soporta:
 *   - bind(string $abstract, Closure $factory)            registro explícito
 *   - singleton(string $abstract, Closure $factory)       una sola instancia
 *   - instance(string $abstract, object $instance)        instancia ya construida
 *   - get(string $abstract)                               resuelve (autowiring si no está bindeado)
 *
 * NO soporta inyección por setter ni por propiedad. Solo constructor injection.
 * Esto es a propósito: forzar dependencias explícitas y testabilidad.
 */
final class Container
{
    /** @var array<string, \Closure> */
    private array $bindings = [];

    /** @var array<string, \Closure> */
    private array $singletons = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, bool> */
    private array $resolving = [];

    /**
     * @template T of object
     * @param class-string<T>|string $abstract
     */
    public function bind(string $abstract, \Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    /**
     * @template T of object
     * @param class-string<T>|string $abstract
     */
    public function singleton(string $abstract, \Closure $factory): void
    {
        $this->singletons[$abstract] = $factory;
    }

    /**
     * @template T of object
     * @param class-string<T>|string $abstract
     * @param T $instance
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * @template T of object
     * @param class-string<T>|string $abstract
     * @return T|object
     */
    public function get(string $abstract): object
    {
        // Instancia ya cacheada
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Singleton: resolver una sola vez y cachear
        if (isset($this->singletons[$abstract])) {
            $factory = $this->singletons[$abstract];
            $instance = $factory($this);
            $this->instances[$abstract] = $instance;
            return $instance;
        }

        // Binding explícito: factory cada vez
        if (isset($this->bindings[$abstract])) {
            $factory = $this->bindings[$abstract];
            return $factory($this);
        }

        // Autowiring: solo si es una clase concreta resoluble
        if (!class_exists($abstract)) {
            throw new ContainerException("No se puede resolver '{$abstract}': no es una clase ni está bindeada.");
        }

        if (isset($this->resolving[$abstract])) {
            throw new ContainerException("Dependencia circular detectada al resolver '{$abstract}'.");
        }

        $this->resolving[$abstract] = true;
        try {
            $instance = $this->autowire($abstract);
        } finally {
            unset($this->resolving[$abstract]);
        }

        return $instance;
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract])
            || isset($this->singletons[$abstract])
            || isset($this->bindings[$abstract])
            || class_exists($abstract);
    }

    /**
     * Construye una clase resolviendo recursivamente sus dependencias.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function autowire(string $class): object
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException("La clase '{$class}' no es instanciable (¿abstracta o interfaz?).");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $params[] = $this->resolveParameter($class, $param);
        }

        return $reflection->newInstanceArgs($params);
    }

    private function resolveParameter(string $owner, ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            throw new ContainerException(
                "No se puede resolver el parámetro '\${$param->getName()}' de '{$owner}': sin tipo de clase ni valor por defecto."
            );
        }

        return $this->get($type->getName());
    }

    /**
     * Llama a un callable resolviendo sus parámetros desde el contenedor.
     *
     * @param array{0: object|class-string, 1: string}|\Closure $callable
     * @param array<string, mixed> $extraParams
     */
    public function call(array|\Closure $callable, array $extraParams = []): mixed
    {
        if ($callable instanceof \Closure) {
            $reflection = new \ReflectionFunction($callable);
            $args = $this->resolveCallableParams($reflection->getParameters(), $extraParams);
            return $callable(...$args);
        }

        [$target, $method] = $callable;
        $instance = is_string($target) ? $this->get($target) : $target;
        $reflection = new \ReflectionMethod($instance, $method);
        $args = $this->resolveCallableParams($reflection->getParameters(), $extraParams);
        return $instance->{$method}(...$args);
    }

    /**
     * @param list<ReflectionParameter> $params
     * @param array<string, mixed> $extraParams
     * @return list<mixed>
     */
    private function resolveCallableParams(array $params, array $extraParams): array
    {
        $args = [];
        foreach ($params as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $extraParams)) {
                $args[] = $extraParams[$name];
                continue;
            }
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
                continue;
            }
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }
            throw new ContainerException(
                "No se puede resolver el parámetro '\${$name}' del callable."
            );
        }
        return $args;
    }
}
