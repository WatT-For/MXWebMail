<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;

/**
 * Décorations saisonnières "paysage" : silhouettes monochromes posées dans
 * le bandeau de l'app (.navbar) et en haut de la carte login/maintenance
 * (.login-card) - bas des headers = sol. Zéro JS, zéro animation : un <style>
 * inline via app.head applique un SVG par événement en mask-image ; la couleur
 * visible vient d'un background-color contrasté avec le fond (variables du
 * thème), le SVG n'est qu'un masque d'alpha.
 * Date de référence = horloge serveur ; Pâques calculé par computus grégorien
 * maison - easter_date() de PHP est décalée par le fuseau horaire.
 */
final class SeasonalDecorations extends PluginBase
{
	/**
	 * Événements - ordre = priorité (premier actif gagnant ; easter avant
	 * aprilfools : Pâques tombe parfois un 1er avril, ex. 2029).
	 * 'on' = clé booléenne du schéma ; 'from'/'to' = clés de fenêtre MM-DD ;
	 * 'before'/'after' = clés d'offsets en jours (Pâques) ; 'f'/'t'/'b'/'a' =
	 * défauts ; 'svg' = asset de la scène (assets/<svg>).
	 * Les clés sont littérales : `check` signale tout réglage du schéma
	 * absent du code.
	 */
	private const EVENTS = [
		'newyear'    => ['on' => 'newyear',    'from' => 'newyear_from',    'to' => 'newyear_to',    'f' => '12-29', 't' => '01-02'],
		'easter'     => ['on' => 'easter',     'before' => 'easter_days_before', 'after' => 'easter_days_after', 'b' => 7, 'a' => 1],
		'aprilfools' => ['on' => 'aprilfools', 'from' => 'aprilfools_from', 'to' => 'aprilfools_to', 'f' => '04-01', 't' => '04-01'],
		'halloween'  => ['on' => 'halloween',  'from' => 'halloween_from',  'to' => 'halloween_to',  'f' => '10-25', 't' => '11-01'],
		'christmas'  => ['on' => 'christmas',  'from' => 'christmas_from',  'to' => 'christmas_to',  'f' => '12-01', 't' => '12-26'],
	];

	public function register(HookRegistry $hooks): void
	{
		$hooks->on('app.head', fn(string $nonce, string $base) => $this->head($base));
	}

	/**
	 * <style> injecté dans <head> - émis uniquement par app.php, login.php et
	 * maintenance.php : la surface admin n'atteint jamais ce code.
	 */
	private function head(string $base): string
	{
		if (!$this->app->isLoggedIn() && empty($this->config['show_on_login'] ?? true)) {
			return '';
		}
		$event = $this->currentEvent();
		if ($event === null) {
			return '';
		}
		$layers = $this->layers($event, $base);
		if ($layers === '') {
			return '';
		}
		// ::before positionné peint après le texte non positionné > les
		// enfants directs passent en relative pour rester au-dessus du paysage.
		$css = '.app-shell>.navbar{position:relative}'
			. '.app-shell>.navbar::before{content:"";position:absolute;inset:0;'
				. 'pointer-events:none;'
				. 'background-color:rgba(var(--mxwm-header-text-rgb,255,255,255),.3);'
				. $layers . '}'
			. '.app-shell>.navbar>*{position:relative}'
			. '.login-card{position:relative;overflow:hidden}'
			. '.login-card::before{content:"";position:absolute;top:0;left:0;right:0;'
				. 'height:56px;pointer-events:none;'
				. 'background-color:rgba(var(--bs-body-color-rgb),.18);'
				. $layers . '}'
			. '.login-card>.card-body{position:relative}';
		return "\n\t" . '<style>' . $css . '</style>';
	}

	/**
	 * Couches mask-* de la scène de $event depuis scenes.json
	 * ({svg, pos, size} par objet - assets/<svg>.svg). Entrée invalide ou
	 * fichier absent > ignorée ; rien de valide > ''.
	 */
	private function layers(string $event, string $base): string
	{
		$dir = dirname(__FILE__);
		$scenes = json_decode(
			(string) @file_get_contents($dir . '/scenes.json'), true);
		$list = is_array($scenes[$event] ?? null) ? $scenes[$event] : [];
		$urls = $pos = $size = [];
		$b = htmlspecialchars($base, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$v = rawurlencode($this->version());
		foreach ($list as $o) {
			$svg = basename((string) ($o['svg'] ?? ''));
			$p = trim((string) ($o['pos'] ?? ''));
			$s = trim((string) ($o['size'] ?? ''));
			if ($svg === '' || $p === '' || $s === ''
				|| !is_file("{$dir}/assets/{$svg}.svg")) {
				continue;
			}
			$urls[] = "url('{$b}/assets/plugin/seasonal-decorations/{$svg}.svg?v={$v}')";
			$pos[] = $p;
			$size[] = $s;
		}
		if (!$urls) {
			return '';
		}
		$img = implode(',', $urls);
		$posj = implode(',', $pos);
		$sizej = implode(',', $size);
		return "-webkit-mask-image:{$img};mask-image:{$img};"
			. "-webkit-mask-position:{$posj};mask-position:{$posj};"
			. "-webkit-mask-size:{$sizej};mask-size:{$sizej};"
			. '-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;';
	}

	/** Premier événement actif à la date serveur du jour, null sinon. */
	private function currentEvent(): ?string
	{
		$today = (int) date('md');
		$year  = (int) date('Y');
		foreach (self::EVENTS as $name => $row) {
			if (!($this->config[$row['on']] ?? true)) {
				continue;
			}
			$hit = isset($row['before'])
				? $this->inEasterWindow($year)
				: $this->inWindow($today,
					$this->md($this->config[$row['from']] ?? null, $row['f']),
					$this->md($this->config[$row['to']] ?? null, $row['t']));
			if ($hit) {
				return $name;
			}
		}
		return null;
	}

	/** Fenêtre MM-DD ; from > to = à cheval sur le changement d'année. */
	private function inWindow(int $day, int $from, int $to): bool
	{
		return $from <= $to
			? ($day >= $from && $day <= $to)
			: ($day >= $from || $day <= $to);
	}

	/** 'MM-DD' valide > entier MMDD ; invalide > défaut. */
	private function md(mixed $v, string $default): int
	{
		$v = is_string($v) ? $v : '';
		if (!preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', $v)) {
			$v = $default;
		}
		return (int) str_replace('-', '', $v);
	}

	/** Fenêtre Pâques : dimanche de Pâques ± offsets (clampés 0-30). */
	private function inEasterWindow(int $year): bool
	{
		$row = self::EVENTS['easter'];
		$before = max(0, min(30, (int) ($this->config[$row['before']] ?? $row['b'])));
		$after  = max(0, min(30, (int) ($this->config[$row['after']]  ?? $row['a'])));
		[$m, $d] = self::easterSunday($year);
		$easter = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $m, $d));
		$today  = date('Y-m-d');
		return $today >= $easter->modify("-{$before} days")->format('Y-m-d')
			&& $today <= $easter->modify("+{$after} days")->format('Y-m-d');
	}

	/** Computus grégorien : [mois, jour] du dimanche de Pâques de $year. */
	private static function easterSunday(int $year): array
	{
		$a = $year % 19;
		$b = intdiv($year, 100); $c = $year % 100;
		$d = intdiv($b, 4);      $e = $b % 4;
		$f = intdiv($b + 8, 25);
		$g = intdiv($b - $f + 1, 3);
		$h = (19 * $a + $b - $d - $g + 15) % 30;
		$i = intdiv($c, 4);      $k = $c % 4;
		$l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
		$m = intdiv($a + 11 * $h + 22 * $l, 451);
		$n = $h + $l - 7 * $m + 114;
		return [intdiv($n, 31), $n % 31 + 1];
	}
}
