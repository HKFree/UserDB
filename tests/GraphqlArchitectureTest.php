<?php

declare(strict_types=1);

namespace Test;

use App\MapiModule\Data\Ap;
use App\MapiModule\Data\CteciSpojeni;
use App\MapiModule\Data\Cteni;
use App\MapiModule\Data\Ja;
use App\MapiModule\Data\Oblast;
use App\MapiModule\Data\PovoleneRole;
use App\MapiModule\Data\Prihlaseny;
use App\MapiModule\Data\PristupOdepren;
use App\MapiModule\Data\Role;
use App\MapiModule\Data\RolePrihlaseneho;
use App\MapiModule\Schema\SchemaFactory;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use Nette\Utils\Finder;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * Rules of issue #264 that are checked on the source code and on the schema, without any request:
 * H2 (resolvers reach only the read layer), H3 (shape of the read layer), H4 (what a signed-in user carries),
 * H5 (no writes), H8 (no secrets) and the schema fingerprint.
 */
final class GraphqlArchitectureTest extends TestCase
{
    private const MODULE = __DIR__ . '/../app/MapiModule';
    private const SCHEMA_NAMESPACE = 'App\\MapiModule\\Schema\\';
    private const DATA_NAMESPACE = 'App\\MapiModule\\Data\\';
    /** Immutable objects the read layer may return; a new one has to be added here on purpose. */
    private const VALUES = [Ja::class, Role::class, Oblast::class, Ap::class, Prihlaseny::class, RolePrihlaseneho::class];
    /** Every other class of the read layer. */
    private const SERVICES = [Cteni::class, CteciSpojeni::class, PovoleneRole::class, PristupOdepren::class];
    private const SCALARS = ['int', 'string', 'bool', 'float'];
    private const SECRET = '~heslo|password|passwd|hash|klic|token~i';
    private const SUPERGLOBALS = ['$_SERVER', '$_GET', '$_POST', '$_COOKIE', '$_FILES', '$_SESSION', '$_ENV', '$_REQUEST', '$GLOBALS'];

    public function testSchemaClassesReachOnlyTheReadLayer(): void {
        $files = $this->files('Schema');
        Assert::contains('QueryType.php', array_keys($files));
        foreach ($files as $name => $source) {
            Assert::same([], $this->namesOutside($source, ['GraphQL\\', self::SCHEMA_NAMESPACE, self::DATA_NAMESPACE]), "$name reaches outside the schema and the read layer.");
            Assert::same([], $this->superglobals($source), "$name reads a superglobal.");
            $class = self::SCHEMA_NAMESPACE . basename($name, '.php');
            Assert::true(class_exists($class), "$name does not declare $class.");
            $constructor = (new \ReflectionClass($class))->getConstructor();
            foreach ($constructor?->getParameters() ?? [] as $parameter) {
                $type = $parameter->getType();
                Assert::true(
                    $type instanceof \ReflectionNamedType && (str_starts_with($type->getName(), self::DATA_NAMESPACE) || str_starts_with($type->getName(), self::SCHEMA_NAMESPACE)),
                    "$name: constructor parameter \${$parameter->getName()} is not the read layer.",
                );
            }
        }
    }

    public function testReadLayerDependsOnlyOnItsConnection(): void {
        $files = $this->files('Data');
        $found = array_map(static fn (string $name): string => basename($name, '.php'), array_keys($files));
        sort($found);
        Assert::same($this->shortNames([...self::VALUES, ...self::SERVICES]), $found, 'List every class of the read layer in this test.');
        foreach ($files as $name => $source) {
            // Only the connection wrapper may touch the database library, and only its Connection.
            $allowed = [self::DATA_NAMESPACE, 'LogicException', 'RuntimeException', ...($name === 'CteciSpojeni.php' ? ['Nette\\Database\\Connection'] : [])];
            Assert::same([], $this->namesOutside($source, $allowed), "$name depends on something outside the read layer.");
            Assert::same([], $this->superglobals($source), "$name reads a superglobal.");
        }
    }

    public function testReadLayerCannotWrite(): void {
        foreach ($this->files('Data') as $name => $source) {
            $tokens = \PhpToken::tokenize($source);
            foreach ($tokens as $index => $token) {
                if ($token->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON])) {
                    $called = strtolower($tokens[$index + 1]->text);
                    Assert::false(in_array($called, ['insert', 'update', 'delete', 'query', 'transaction', 'begintransaction', 'commit'], true) && $name !== 'CteciSpojeni.php', "$name calls $called().");
                }
                if ($token->is([T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE])) {
                    Assert::notMatch('~\b(INSERT|UPDATE|DELETE|REPLACE|TRUNCATE|ALTER|DROP|CREATE|GRANT|CALL|LOCK|SET|INTO)\b~i', $token->text, "$name holds SQL that is not a plain SELECT.");
                }
            }
        }
        $methods = array_map(static fn (\ReflectionMethod $method): string => $method->getName(), (new \ReflectionClass(CteciSpojeni::class))->getMethods(\ReflectionMethod::IS_PUBLIC));
        Assert::same(['__construct', 'vyber'], $methods);
        // The wrapper refuses a write before it reaches the connection: the offline connection would fail differently.
        $spojeni = new CteciSpojeni(new OfflineConnection());
        foreach (['UPDATE Ap SET jmeno = ?', 'DELETE FROM Ap', 'SELECT 1; DELETE FROM Ap', ' select 1', 'SELECTED'] as $sql) {
            Assert::exception(static fn () => $spojeni->vyber($sql, 'x'), \LogicException::class, 'Čtecí vrstva smí jen jednoduchý SELECT.');
        }
        Assert::exception(static fn () => $spojeni->vyber('SELECT id FROM Ap'), \LogicException::class, 'Database access is forbidden in this test double.');
    }

    public function testReaderTakesTheSignedInUserFirstAndReturnsOnlyValues(): void {
        $methods = (new \ReflectionClass(Cteni::class))->getMethods(\ReflectionMethod::IS_PUBLIC);
        $checked = [];
        foreach ($methods as $method) {
            if ($method->isConstructor()) {
                continue;
            }
            $checked[] = $method->getName();
            $first = $method->getParameters()[0] ?? null;
            $type = $first?->getType();
            Assert::true($type instanceof \ReflectionNamedType && $type->getName() === Prihlaseny::class, "Cteni::{$method->getName()}() does not take the signed-in user first.");
            $this->assertValueType($method->getReturnType(), $method->getDocComment(), '@return', "Cteni::{$method->getName()}()");
        }
        Assert::contains('ja', $checked);
    }

    public function testValuesAreImmutableAndHoldOnlyListedData(): void {
        foreach (self::VALUES as $class) {
            $reflection = new \ReflectionClass($class);
            Assert::true($reflection->isFinal() && $reflection->isReadOnly(), "$class must be final and readonly.");
            Assert::same([], array_map(static fn (\ReflectionProperty $property): string => $property->getName(), $reflection->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE)), "$class hides a property.");
            foreach ($reflection->getProperties() as $property) {
                $this->assertValueType($property->getType(), $property->getDocComment(), '@var', "$class::\${$property->getName()}");
            }
        }
    }

    public function testSignedInUserCarriesOnlyUidNickAndRoles(): void {
        Assert::same(['uid', 'nick', 'role'], $this->propertyNames(Prihlaseny::class));
        Assert::same(['typ', 'oblastId'], $this->propertyNames(RolePrihlaseneho::class));
    }

    public function testAllowedRolesAreListedInOnePlace(): void {
        $holders = [];
        foreach (Finder::findFiles('*.php')->from(self::MODULE) as $file) {
            if (str_contains((string) file_get_contents((string) $file), 'EXTSUPPORT')) {
                $holders[] = $file->getFilename();
            }
        }
        Assert::same(['PovoleneRole.php'], $holders);
    }

    public function testSchemaHasNoWritesAndEveryFieldItsOwnResolver(): void {
        $schema = $this->schema();
        $schema->assertValid();
        Assert::null($schema->getMutationType());
        Assert::null($schema->getSubscriptionType());
        $fields = [];
        foreach ($this->ownTypes($schema) as $type) {
            foreach ($type->getFields() as $field) {
                $fields[] = "$type->name.$field->name";
                Assert::true($field->resolveFn !== null, "$type->name.$field->name relies on the default resolver.");
            }
        }
        Assert::contains('Query.ja', $fields);
        Assert::contains('Ap.jmeno', $fields);
    }

    public function testNothingIsNamedLikeASecret(): void {
        $names = [];
        foreach ($this->ownTypes($this->schema()) as $type) {
            $names[] = $type->name;
            foreach ($type->getFields() as $field) {
                $names[] = $field->name;
                foreach ($field->args as $argument) {
                    $names[] = $argument->name;
                }
            }
        }
        foreach (self::VALUES as $class) {
            array_push($names, ...$this->propertyNames($class));
        }
        Assert::contains('jmeno', $names);
        Assert::same([], array_values(preg_grep(self::SECRET, $names) ?: []));
        // Column lists live in the sources of the read layer.
        foreach ($this->files('Data') as $name => $source) {
            Assert::notMatch(self::SECRET, $source, "$name mentions a secret column.");
        }
        Assert::match(self::SECRET, 'heslo_hash');
    }

    public function testSchemaMatchesItsFingerprint(): void {
        Assert::same(file_get_contents(self::MODULE . '/schema.graphql'), SchemaPrinter::doPrint($this->schema()), 'The schema changed: update app/MapiModule/schema.graphql in the same change.');
    }

    private function schema(): Schema {
        return (new SchemaFactory(new Cteni(new CteciSpojeni(new OfflineConnection()))))->vytvor();
    }

    /** @return list<ObjectType> */
    private function ownTypes(Schema $schema): array {
        $types = [];
        foreach ($schema->getTypeMap() as $type) {
            if ($type instanceof ObjectType && !Introspection::isIntrospectionType($type)) {
                $types[] = $type;
            }
        }
        return $types;
    }

    private function assertValueType(?\ReflectionType $type, string|false $doc, string $tag, string $where): void {
        Assert::true($type instanceof \ReflectionNamedType, "$where needs one declared type.");
        $name = $type->getName();
        if ($name === 'array') {
            // A list must say what it holds; PHPStan keeps the annotation in step with the code.
            Assert::true(preg_match('~' . $tag . ' list<(\w+)>~', (string) $doc, $match) === 1, "$where: array needs $tag list<Type>.");
            $name = in_array($match[1], self::SCALARS, true) ? $match[1] : self::DATA_NAMESPACE . $match[1];
        }
        Assert::true(in_array($name, [...self::SCALARS, ...self::VALUES], true), "$where exposes $name.");
    }

    /**
     * @param class-string $class
     * @return list<string>
     */
    private function propertyNames(string $class): array {
        return array_map(static fn (\ReflectionProperty $property): string => $property->getName(), (new \ReflectionClass($class))->getProperties());
    }

    /**
     * @param list<class-string> $classes
     * @return list<string>
     */
    private function shortNames(array $classes): array {
        $names = array_map(static fn (string $class): string => substr($class, strlen(self::DATA_NAMESPACE)), $classes);
        sort($names);
        return $names;
    }

    /** @return array<string, string> file name => source, sorted */
    private function files(string $directory): array {
        $files = [];
        foreach (Finder::findFiles('*.php')->in(self::MODULE . '/' . $directory) as $file) {
            $files[$file->getFilename()] = (string) file_get_contents((string) $file);
        }
        ksort($files);
        return $files;
    }

    /**
     * Imported and qualified names that are not allowed. An unqualified name is either imported (and checked
     * there), in the file's own namespace, or a PHP built-in function.
     * @param list<string> $allowed a namespace prefix ending with a backslash, or one exact name
     * @return list<string>
     */
    private function namesOutside(string $source, array $allowed): array {
        $outside = [];
        $tokens = \PhpToken::tokenize($source);
        foreach ($tokens as $index => $token) {
            $name = null;
            if ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                $name = ltrim($token->text, '\\');
            } elseif ($token->is(T_USE) && $tokens[$index + 2]->is(T_STRING)) {
                // "use Nette;" would let a qualified name start with an innocent-looking alias.
                $name = $tokens[$index + 2]->text;
            } elseif ($token->is(T_AS) && $tokens[$index + 2]->is(T_STRING)) {
                $name = 'alias ' . $tokens[$index + 2]->text;
            }
            if ($name !== null && array_filter($allowed, static fn (string $entry): bool => $name === rtrim($entry, '\\') || (str_ends_with($entry, '\\') && str_starts_with($name, $entry))) === []) {
                $outside[] = $name;
            }
        }
        return $outside;
    }

    /** @return list<string> */
    private function superglobals(string $source): array {
        $found = [];
        foreach (\PhpToken::tokenize($source) as $token) {
            if ($token->is(T_VARIABLE) && in_array($token->text, self::SUPERGLOBALS, true)) {
                $found[] = $token->text;
            }
        }
        return $found;
    }
}

(new GraphqlArchitectureTest())->run();
