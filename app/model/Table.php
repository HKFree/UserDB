<?php

namespace App\Model;

use Nette;
use Nette\Database\Context;

/**
 * Reprezentuje repozitář pro databázovou tabulku
 */
abstract class Table
{
    /**
     * @var Nette\Database\Context
     */
    protected $connection;

    /**
     *
     * @var Nette\Security\User
     */
    protected $userService;

    /**
     * @var string
     */
    protected $tableName;

    protected int $loginUID = 797;

    /**
     * @param Nette\Database\Context $db
     * @throws \Nette\InvalidStateException
     */
    public function __construct(Nette\Database\Context $db, Nette\Security\User $user) {
        $this->connection = $db;
        $this->userService = $user;

        if ($this->tableName === null) {
            $class = get_class($this);
            throw new Nette\InvalidStateException("Název tabulky musí být definován v $class::\$tableName.");
        }
    }

    /**
     * Vrací celou tabulku z databáze
     * @return \Nette\Database\Table\Selection<\Nette\Database\Table\ActiveRow>
     */
    protected function getTable(): Nette\Database\Table\Selection {
        return $this->connection->table($this->tableName);
    }

    /**
     * Vrací spojení do databáze (context)
     * @return \Nette\Database\Context
     */
    protected function getConnection(): Context {
        return $this->connection;
    }

    /**
     * Vrací všechny záznamy z databáze
     * @return \Nette\Database\Table\Selection<\Nette\Database\Table\ActiveRow>
     */
    public function findAll(): Nette\Database\Table\Selection {
        return $this->getTable();
    }

    /**
     * Vrací vyfiltrované záznamy na základě vstupního pole
     * (pole array('name' => 'David') se převede na část SQL dotazu WHERE name = 'David')
     *
     * @param array<array-key, mixed> $by
     *
     * @return \Nette\Database\Table\Selection<\Nette\Database\Table\ActiveRow>
     */
    public function findBy(array $by): Nette\Database\Table\Selection {
        return $this->getTable()->where($by);
    }

    /**
     * To samé jako findBy akorát vrací vždy jen jeden záznam
     *
     * @param array<array-key, mixed> $by
     *
     * @return \Nette\Database\Table\ActiveRow|null
     */
    public function findOneBy(array $by): ?Nette\Database\Table\ActiveRow {
        return $this->findBy($by)->limit(1)->fetch();
    }

    /**
     * Vrací záznam s daným primárním klíčem
     *
     * @param int|string $id
     *
     * @return \Nette\Database\Table\ActiveRow|null
     */
    public function find($id): ?Nette\Database\Table\ActiveRow {
        return $this->getTable()->get($id);
    }
    /**
     * Smaže záznam/y
     *
     * @param array<array-key, mixed> $by
     *
     * @return int Počet smazaných řádků
     */
    public function delete(array $by): int {
        return $this->getTable()->where($by)->delete();
    }

    /**
     * @param iterable<array-key, mixed> $data
     * @return Nette\Database\Table\ActiveRow|array<array-key, mixed>|int|bool
     */
    public function insert(iterable $data): Nette\Database\Table\ActiveRow|array|int|bool {
        return ($this->getTable()->insert($data));
    }

    /**
     * @param int|string $id
     * @param iterable<array-key, mixed> $data
     */
    public function update($id, iterable $data): Nette\Database\Table\ActiveRow {
        $post = $this->find($id);
        $post->update($data);
        return ($post);
    }
}
