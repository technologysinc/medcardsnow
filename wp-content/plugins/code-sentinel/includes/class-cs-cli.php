<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-CLI: wp code-sentinel scan [--target=<target>] [--format=table|json|csv] [--min-severity=<sev>]
 */
class CS_CLI {

	/**
	 * Scan for active and passive issues.
	 *
	 * ## OPTIONS
	 * [--target=<target>] : active_plugins | all_plugins | active_theme | themes | mu | uploads | everything | plugin:<slug>
	 * [--core]            : Also verify WordPress core checksums.
	 * [--format=<format>] : table (default), json, csv.
	 * [--min-severity=<s>]: critical | high | medium | low | info (default info).
	 */
	public function scan( $args, $assoc ) {
		$target = isset( $assoc['target'] ) ? $assoc['target'] : 'active_plugins';
		$format = isset( $assoc['format'] ) ? $assoc['format'] : 'table';
		$min    = isset( $assoc['min-severity'] ) ? $assoc['min-severity'] : 'info';
		$minR   = CS_Util::sev_rank( $min );

		WP_CLI::log( 'Scanning: ' . $target );
		$scanner = new CS_Scanner();
		$result  = $scanner->run( array( $target ) );
		foreach ( CS_Checks::run( array( $target ) ) as $row ) {
			$m = CS_Rules::meta_map();
			if ( isset( $m[ $row['rule'] ] ) ) {
				$row = array_merge( $row, array( 'sev' => $m[ $row['rule'] ]['sev'], 'cat' => $m[ $row['rule'] ]['cat'], 'title' => $m[ $row['rule'] ]['title'], 'fix' => $m[ $row['rule'] ]['fix'] ) );
				$result['findings'][] = $row;
			}
		}
		if ( ! empty( $assoc['core'] ) ) {
			$m = CS_Rules::meta_map();
			foreach ( CS_Checks::core_integrity() as $row ) {
				$row = array_merge( $row, array( 'sev' => $m[ $row['rule'] ]['sev'], 'cat' => $m[ $row['rule'] ]['cat'], 'title' => $m[ $row['rule'] ]['title'], 'fix' => '' ) );
				$result['findings'][] = $row;
			}
		}

		$rows = array();
		foreach ( $result['findings'] as $f ) {
			if ( CS_Util::sev_rank( $f['sev'] ) < $minR ) {
				continue;
			}
			$rows[] = array(
				'severity' => $f['sev'],
				'issue'    => $f['title'],
				'file'     => $f['rel'] . ( $f['line'] ? ':' . $f['line'] : '' ),
				'detail'   => $f['snippet'],
			);
		}

		if ( 'json' === $format ) {
			WP_CLI::print_value( $result, array( 'format' => 'json' ) );
		} elseif ( 'csv' === $format ) {
			WP_CLI\Utils\format_items( 'csv', $rows, array( 'severity', 'issue', 'file', 'detail' ) );
		} else {
			WP_CLI\Utils\format_items( 'table', $rows, array( 'severity', 'issue', 'file', 'detail' ) );
		}

		$s = $result['severity'];
		WP_CLI::log( sprintf( 'Scanned %d file(s). Critical:%d High:%d Medium:%d Low:%d Info:%d',
			$result['stats']['scanned'], $s['critical'], $s['high'], $s['medium'], $s['low'], $s['info'] ) );

		if ( $s['critical'] > 0 ) {
			WP_CLI::halt( 2 );
		}
	}
}
