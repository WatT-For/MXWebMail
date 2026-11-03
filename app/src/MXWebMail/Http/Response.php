<?php
declare(strict_types=1);

namespace MXWebMail\Http;

class Response
{
	protected ?string $file = null;

	public function __construct(
		protected string $body = '',
		protected int $status = 200,
		protected array $headers = []
	) {}

	/**
	 * Réponse dont le corps est streamé depuis un fichier disque - mémoire
	 * constante quelle que soit la taille (pièces jointes, archives).
	 */
	public static function file(string $path, int $status = 200, array $headers = []): self
	{
		$r = new self('', $status, $headers);
		$r->file = $path;
		return $r;
	}

	public function send(): void
	{
		http_response_code($this->status);
		foreach ($this->headers as $name => $value) {
			header("{$name}: {$value}");
		}
		if ($this->file !== null) {
			$fp = fopen($this->file, 'rb');
			if (is_resource($fp)) {
				\MailSo\Base\Utils::FpassthruWithTimeLimitReset($fp);
				fclose($fp);
			}
			return;
		}
		echo $this->body;
	}

	/**
	 * Compresse le corps en gzip si le type le permet. Pose toujours
	 * "Vary: Accept-Encoding" dès que le type est compressible (même si le
	 * corps est trop petit ou le client ne l'accepte pas) - un cache partagé
	 * doit distinguer les variantes. Retourne true si le corps a été compressé.
	 */
	public function gzip(int $level, int $minSize, bool $accepted): bool
	{
		// Un corps fichier n'est jamais compressé : gzencode l'exigerait en
		// mémoire (le but du fichier est justement la mémoire constante) et
		// garderait un Content-Length incohérent.
		if ($this->status >= 300 || $this->file !== null
			|| isset($this->headers['Content-Encoding'])) {
			return false;
		}
		$type = (string) ($this->headers['Content-Type'] ?? '');
		$compressible = str_starts_with($type, 'text/')
			|| (bool) preg_match('/json|javascript|xml|svg/i', $type);
		if (!$compressible) {
			return false;
		}
		$vary = (string) ($this->headers['Vary'] ?? '');
		if (stripos($vary, 'Accept-Encoding') === false) {
			$this->headers['Vary'] = trim($vary . ($vary !== '' ? ', ' : '') . 'Accept-Encoding');
		}
		if (!$accepted || strlen($this->body) < $minSize) {
			return false;
		}
		$compressed = gzencode($this->body, $level);
		if ($compressed === false) {
			return false;
		}
		$this->body = $compressed;
		$this->headers['Content-Encoding'] = 'gzip';
		return true;
	}

	public static function html(string $body, int $status = 200): self
	{
		return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
	}

	public static function redirect(string $location): self
	{
		return new self('', 302, ['Location' => $location]);
	}

	public static function notFound(): self
	{
		return new self('404 - Page introuvable', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
	}
}
