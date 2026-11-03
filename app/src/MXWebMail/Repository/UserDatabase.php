<?php
declare(strict_types=1);

namespace MXWebMail\Repository;

/**
 * Bases SQLite par utilisateur : storage/db/users/<id>.sqlite.
 * Isolent les données volumineuses/personnelles (contacts pour l'instant) :
 * une sync massive ou une corruption n'impacte que le fichier de l'utilisateur.
 */
final class UserDatabase
{
	/** @var array<int, Database> pool de connexions, une par utilisateur sollicité */
	private array $pool = [];

	public function __construct(private string $storageDir) {}

	/** Base de l'utilisateur : créée et migrée à la première ouverture. */
	public function for(int $userId): Database
	{
		if (!isset($this->pool[$userId])) {
			$dir = $this->dir();
			if (!is_dir($dir)) {
				mkdir($dir, 0700, true);
			}
			$db = Database::sqliteFile($this->pathFor($userId));
			$db->migrate();
			$this->pool[$userId] = $db;
		}
		return $this->pool[$userId];
	}

	public function pathFor(int $userId): string
	{
		return $this->dir() . '/' . $userId . '.sqlite';
	}

	public function exists(int $userId): bool
	{
		return is_file($this->pathFor($userId));
	}

	/** Suppression : remplace l'ancien cascade FK contacts.user_id > users.id. */
	public function deleteFor(int $userId): void
	{
		unset($this->pool[$userId]); // ferme le PDO - unlink impossible sinon (Windows)
		foreach (['', '-wal', '-shm', '-journal'] as $suffix) {
			@unlink($this->pathFor($userId) . $suffix);
		}
	}

	private function dir(): string
	{
		return $this->storageDir . '/db/users';
	}
}
