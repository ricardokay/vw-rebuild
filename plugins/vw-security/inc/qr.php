<?php
/**
 * Minimal QR encoder (byte mode, error-correction level M, versions 1–10),
 * so the 2FA enrollment QR never leaves the server. Follows ISO/IEC 18004.
 */

defined( 'ABSPATH' ) || exit;

function vw_qr_matrix( string $text ): ?array {
	$ecc_per_block = [ -1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26 ];
	$num_blocks    = [ -1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5 ];

	$len = strlen( $text );
	$ver = 0;
	for ( $v = 1; $v <= 10; $v++ ) {
		$cap_bits = vw_qr_data_codewords( $v, $ecc_per_block, $num_blocks ) * 8;
		$need     = 4 + ( $v <= 9 ? 8 : 16 ) + 8 * $len;
		if ( $need <= $cap_bits ) {
			$ver = $v;
			break;
		}
	}
	if ( ! $ver ) {
		return null;
	}

	$data_cw = vw_qr_data_codewords( $ver, $ecc_per_block, $num_blocks );
	$bits    = [];
	$push    = function ( int $val, int $n ) use ( &$bits ) {
		for ( $i = $n - 1; $i >= 0; $i-- ) {
			$bits[] = ( $val >> $i ) & 1;
		}
	};
	$push( 4, 4 );
	$push( $len, $ver <= 9 ? 8 : 16 );
	for ( $i = 0; $i < $len; $i++ ) {
		$push( ord( $text[ $i ] ), 8 );
	}
	$cap = $data_cw * 8;
	$push( 0, min( 4, $cap - count( $bits ) ) );
	$push( 0, ( 8 - count( $bits ) % 8 ) % 8 );
	for ( $pad = 0xEC; count( $bits ) < $cap; $pad ^= 0xEC ^ 0x11 ) {
		$push( $pad, 8 );
	}
	$data = [];
	foreach ( array_chunk( $bits, 8 ) as $byte ) {
		$data[] = bindec( implode( '', $byte ) );
	}

	// Split into blocks, append Reed–Solomon ECC, interleave.
	$nb       = $num_blocks[ $ver ];
	$ecc_len  = $ecc_per_block[ $ver ];
	$raw_cw   = intdiv( vw_qr_raw_modules( $ver ), 8 );
	$n_short  = $nb - $raw_cw % $nb;
	$short_ln = intdiv( $raw_cw, $nb );
	$divisor  = vw_qr_rs_divisor( $ecc_len );
	$blocks   = [];
	for ( $i = 0, $k = 0; $i < $nb; $i++ ) {
		$dlen = $short_ln - $ecc_len + ( $i < $n_short ? 0 : 1 );
		$dat  = array_slice( $data, $k, $dlen );
		$k   += $dlen;
		$ecc  = vw_qr_rs_remainder( $dat, $divisor );
		if ( $i < $n_short ) {
			$dat[] = 0;
		}
		$blocks[] = array_merge( $dat, $ecc );
	}
	$codewords = [];
	$blen      = count( $blocks[0] );
	for ( $i = 0; $i < $blen; $i++ ) {
		foreach ( $blocks as $j => $blk ) {
			if ( $i !== $short_ln - $ecc_len || $j >= $n_short ) {
				$codewords[] = $blk[ $i ];
			}
		}
	}

	$size = $ver * 4 + 17;
	$m    = array_fill( 0, $size, array_fill( 0, $size, 0 ) );
	$fn   = $m;
	$set  = function ( int $x, int $y, int $dark ) use ( &$m, &$fn ) {
		$m[ $y ][ $x ]  = $dark;
		$fn[ $y ][ $x ] = 1;
	};

	for ( $i = 0; $i < $size; $i++ ) {
		$set( 6, $i, (int) ( 0 === $i % 2 ) );
		$set( $i, 6, (int) ( 0 === $i % 2 ) );
	}
	foreach ( [ [ 3, 3 ], [ $size - 4, 3 ], [ 3, $size - 4 ] ] as [ $cx, $cy ] ) {
		for ( $dy = -4; $dy <= 4; $dy++ ) {
			for ( $dx = -4; $dx <= 4; $dx++ ) {
				$x = $cx + $dx;
				$y = $cy + $dy;
				if ( $x >= 0 && $x < $size && $y >= 0 && $y < $size ) {
					$d = max( abs( $dx ), abs( $dy ) );
					$set( $x, $y, (int) ( 2 !== $d && 4 !== $d ) );
				}
			}
		}
	}
	if ( $ver >= 2 ) {
		$na   = intdiv( $ver, 7 ) + 2;
		$step = intdiv( $ver * 8 + $na * 3 + 5, $na * 4 - 4 ) * 2;
		$pos  = [ 6 ];
		for ( $i = $na - 1, $p = $size - 7; $i >= 1; $i--, $p -= $step ) {
			$pos[ $i ] = $p;
		}
		ksort( $pos );
		$pos  = array_values( $pos );
		$last = count( $pos ) - 1;
		foreach ( $pos as $i => $ax ) {
			foreach ( $pos as $j => $ay ) {
				if ( ( 0 === $i && 0 === $j ) || ( 0 === $i && $last === $j ) || ( $last === $i && 0 === $j ) ) {
					continue;
				}
				for ( $dy = -2; $dy <= 2; $dy++ ) {
					for ( $dx = -2; $dx <= 2; $dx++ ) {
						$set( $ax + $dx, $ay + $dy, (int) ( 1 !== max( abs( $dx ), abs( $dy ) ) ) );
					}
				}
			}
		}
	}
	vw_qr_draw_format( $set, $size, 0 );
	if ( $ver >= 7 ) {
		$rem = $ver;
		for ( $i = 0; $i < 12; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1F25 );
		}
		$vbits = ( $ver << 12 ) | $rem;
		for ( $i = 0; $i < 18; $i++ ) {
			$bit = ( $vbits >> $i ) & 1;
			$a   = $size - 11 + $i % 3;
			$b   = intdiv( $i, 3 );
			$set( $a, $b, $bit );
			$set( $b, $a, $bit );
		}
	}

	$i     = 0;
	$total = count( $codewords ) * 8;
	for ( $right = $size - 1; $right >= 1; $right -= 2 ) {
		if ( 6 === $right ) {
			$right = 5;
		}
		for ( $vert = 0; $vert < $size; $vert++ ) {
			for ( $j = 0; $j < 2; $j++ ) {
				$x  = $right - $j;
				$up = 0 === ( ( $right + 1 ) & 2 );
				$y  = $up ? $size - 1 - $vert : $vert;
				if ( ! $fn[ $y ][ $x ] && $i < $total ) {
					$m[ $y ][ $x ] = ( $codewords[ $i >> 3 ] >> ( 7 - ( $i & 7 ) ) ) & 1;
					$i++;
				}
			}
		}
	}

	$best     = null;
	$best_pen = PHP_INT_MAX;
	for ( $mask = 0; $mask < 8; $mask++ ) {
		$t = $m;
		for ( $y = 0; $y < $size; $y++ ) {
			for ( $x = 0; $x < $size; $x++ ) {
				if ( ! $fn[ $y ][ $x ] && vw_qr_mask_bit( $mask, $x, $y ) ) {
					$t[ $y ][ $x ] ^= 1;
				}
			}
		}
		$tset = function ( int $x, int $y, int $dark ) use ( &$t ) {
			$t[ $y ][ $x ] = $dark;
		};
		vw_qr_draw_format( $tset, $size, $mask );
		$pen = vw_qr_penalty( $t, $size );
		if ( $pen < $best_pen ) {
			$best_pen = $pen;
			$best     = $t;
		}
	}
	return $best;
}

function vw_qr_raw_modules( int $ver ): int {
	$r = ( 16 * $ver + 128 ) * $ver + 64;
	if ( $ver >= 2 ) {
		$na = intdiv( $ver, 7 ) + 2;
		$r -= ( 25 * $na - 10 ) * $na - 55;
		if ( $ver >= 7 ) {
			$r -= 36;
		}
	}
	return $r;
}

function vw_qr_data_codewords( int $ver, array $ecc, array $nb ): int {
	return intdiv( vw_qr_raw_modules( $ver ), 8 ) - $ecc[ $ver ] * $nb[ $ver ];
}

function vw_qr_gf_mul( int $x, int $y ): int {
	$z = 0;
	for ( $i = 7; $i >= 0; $i-- ) {
		$z  = ( $z << 1 ) ^ ( ( $z >> 7 ) * 0x11D );
		$z ^= ( ( $y >> $i ) & 1 ) * $x;
	}
	return $z;
}

function vw_qr_rs_divisor( int $degree ): array {
	$r      = array_fill( 0, $degree, 0 );
	$r[ $degree - 1 ] = 1;
	$root   = 1;
	for ( $i = 0; $i < $degree; $i++ ) {
		for ( $j = 0; $j < $degree; $j++ ) {
			$r[ $j ] = vw_qr_gf_mul( $r[ $j ], $root );
			if ( $j + 1 < $degree ) {
				$r[ $j ] ^= $r[ $j + 1 ];
			}
		}
		$root = vw_qr_gf_mul( $root, 0x02 );
	}
	return $r;
}

function vw_qr_rs_remainder( array $data, array $divisor ): array {
	$r = array_fill( 0, count( $divisor ), 0 );
	foreach ( $data as $b ) {
		$factor = $b ^ array_shift( $r );
		$r[]    = 0;
		foreach ( $divisor as $i => $coef ) {
			$r[ $i ] ^= vw_qr_gf_mul( $coef, $factor );
		}
	}
	return $r;
}

function vw_qr_draw_format( callable $set, int $size, int $mask ): void {
	$data = ( 0 << 3 ) | $mask; // Level M = 0b00.
	$rem  = $data;
	for ( $i = 0; $i < 10; $i++ ) {
		$rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
	}
	$bits = ( ( $data << 10 ) | $rem ) ^ 0x5412;
	$b    = fn( $i ) => ( $bits >> $i ) & 1;
	for ( $i = 0; $i <= 5; $i++ ) {
		$set( 8, $i, $b( $i ) );
	}
	$set( 8, 7, $b( 6 ) );
	$set( 8, 8, $b( 7 ) );
	$set( 7, 8, $b( 8 ) );
	for ( $i = 9; $i < 15; $i++ ) {
		$set( 14 - $i, 8, $b( $i ) );
	}
	for ( $i = 0; $i < 8; $i++ ) {
		$set( $size - 1 - $i, 8, $b( $i ) );
	}
	for ( $i = 8; $i < 15; $i++ ) {
		$set( 8, $size - 15 + $i, $b( $i ) );
	}
	$set( 8, $size - 8, 1 );
}

function vw_qr_mask_bit( int $mask, int $x, int $y ): bool {
	switch ( $mask ) {
		case 0: return 0 === ( $x + $y ) % 2;
		case 1: return 0 === $y % 2;
		case 2: return 0 === $x % 3;
		case 3: return 0 === ( $x + $y ) % 3;
		case 4: return 0 === ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2;
		case 5: return 0 === $x * $y % 2 + $x * $y % 3;
		case 6: return 0 === ( $x * $y % 2 + $x * $y % 3 ) % 2;
		default: return 0 === ( ( $x + $y ) % 2 + $x * $y % 3 ) % 2;
	}
}

function vw_qr_penalty( array $t, int $size ): int {
	$pen   = 0;
	$lines = [];
	for ( $i = 0; $i < $size; $i++ ) {
		$lines[] = implode( '', $t[ $i ] );
		$lines[] = implode( '', array_column( $t, $i ) );
	}
	foreach ( $lines as $line ) {
		if ( preg_match_all( '/0{5,}|1{5,}/', $line, $runs ) ) {
			foreach ( $runs[0] as $run ) {
				$pen += 3 + strlen( $run ) - 5;
			}
		}
		$pen += 40 * ( substr_count( '0000' . $line . '0000', '10111010000' ) + substr_count( '0000' . $line . '0000', '00001011101' ) );
	}
	$dark = 0;
	for ( $y = 0; $y < $size; $y++ ) {
		for ( $x = 0; $x < $size; $x++ ) {
			$dark += $t[ $y ][ $x ];
			if ( $x < $size - 1 && $y < $size - 1 ) {
				$c = $t[ $y ][ $x ];
				if ( $c === $t[ $y ][ $x + 1 ] && $c === $t[ $y + 1 ][ $x ] && $c === $t[ $y + 1 ][ $x + 1 ] ) {
					$pen += 3;
				}
			}
		}
	}
	$total = $size * $size;
	$pen  += 10 * max( 0, (int) ceil( abs( $dark * 20 - $total * 10 ) / $total ) - 1 );
	return $pen;
}

function vw_qr_svg( string $text, int $module_px = 5 ): string {
	$m = vw_qr_matrix( $text );
	if ( ! $m ) {
		return '';
	}
	$size = count( $m );
	$dim  = $size + 8;
	$path = '';
	foreach ( $m as $y => $row ) {
		foreach ( $row as $x => $dark ) {
			if ( $dark ) {
				$path .= 'M' . ( $x + 4 ) . ' ' . ( $y + 4 ) . 'h1v1h-1z';
			}
		}
	}
	$px = $dim * $module_px;
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dim . ' ' . $dim . '" width="' . $px . '" height="' . $px
		. '" shape-rendering="crispEdges" role="img" aria-label="QR code for your authenticator app">'
		. '<rect width="100%" height="100%" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
}
