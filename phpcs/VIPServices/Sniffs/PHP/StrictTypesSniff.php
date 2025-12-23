<?php
/**
 * Strict Types Sniff for VIP Services.
 *
 * Ensures that all PHP files contain the strict_types declaration.
 *
 * @package VIPServices\Sniffs\PHP
 */

namespace VIPServices\Sniffs\PHP;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Class StrictTypesSniff
 *
 * Checks that PHP files have declare(strict_types=1) at the top.
 */
class StrictTypesSniff implements Sniff {

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register(): array {
		return [ T_OPEN_TAG ];
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ): void {
		$tokens = $phpcs_file->getTokens();

		// Only check the first opening tag in the file.
		// Find the first opening tag (skip any inline HTML or comments before it).
		$first_open_tag = $phpcs_file->findNext( T_OPEN_TAG, 0 );
		if ( false === $first_open_tag || $stack_ptr !== $first_open_tag ) {
			return;
		}

		// Look for declare statement after the opening tag (skip comments/whitespace).
		// Note: PHPCS breaks docblocks into multiple tokens (open, string, close, star, etc).
		$next_meaningful = $phpcs_file->findNext(
			[
				T_WHITESPACE,
				T_COMMENT,
				T_DOC_COMMENT,
				T_DOC_COMMENT_OPEN_TAG,
				T_DOC_COMMENT_CLOSE_TAG,
				T_DOC_COMMENT_STRING,
				T_DOC_COMMENT_STAR,
				T_DOC_COMMENT_TAG,
				T_DOC_COMMENT_WHITESPACE,
			],
			( $stack_ptr + 1 ),
			null,
			true
		);

		if ( false === $next_meaningful ) {
			$this->addError( $phpcs_file, $stack_ptr );
			return;
		}

		// Check if it's a declare statement.
		if ( T_DECLARE !== $tokens[ $next_meaningful ]['code'] ) {
			$this->addError( $phpcs_file, $stack_ptr );
			return;
		}

		// Find the opening parenthesis of declare.
		$open_paren = $phpcs_file->findNext( T_OPEN_PARENTHESIS, $next_meaningful );
		if ( false === $open_paren ) {
			$this->addError( $phpcs_file, $stack_ptr );
			return;
		}

		// Find the closing parenthesis.
		$close_paren = $phpcs_file->findNext( T_CLOSE_PARENTHESIS, $open_paren );
		if ( false === $close_paren ) {
			$this->addError( $phpcs_file, $stack_ptr );
			return;
		}

		// Get content between parentheses.
		$declare_content = '';
		for ( $i = $open_paren + 1; $i < $close_paren; $i++ ) {
			if ( T_WHITESPACE !== $tokens[ $i ]['code'] ) {
				$declare_content .= $tokens[ $i ]['content'];
			}
		}

		// Check if strict_types=1 is present.
		if ( false === strpos( $declare_content, 'strict_types=1' ) ) {
			$this->addError( $phpcs_file, $stack_ptr );
		}
	}

	/**
	 * Add error for missing strict types declaration.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token.
	 *
	 * @return void
	 */
	private function addError( File $phpcs_file, int $stack_ptr ): void {
		$phpcs_file->addError(
			'PHP file must start with declare(strict_types=1); after the opening <?php tag',
			$stack_ptr,
			'MissingStrictTypes'
		);
	}
}