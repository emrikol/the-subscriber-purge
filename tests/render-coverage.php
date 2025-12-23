<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$coverage_file = __DIR__ . '/coverage/coverage.php';

if ( ! file_exists( $coverage_file ) ) {
	fwrite( STDERR, "Coverage data not found at {$coverage_file}\n" );
	exit( 1 );
}

$cov = include $coverage_file;

// Disable uncovered file processing to avoid issues when generating reports.
$ref = new ReflectionObject( $cov );
foreach ( array( 'includeUncoveredFiles', 'processUncoveredFiles' ) as $prop_name ) {
	if ( $ref->hasProperty( $prop_name ) ) {
		$prop = $ref->getProperty( $prop_name );
		$prop->setAccessible( true );
		$prop->setValue( $cov, false );
	}
}

( new SebastianBergmann\CodeCoverage\Report\Clover() )->process( $cov, __DIR__ . '/coverage/coverage.xml' );
( new SebastianBergmann\CodeCoverage\Report\Html\Facade( 50, 90 ) )->process( $cov, __DIR__ . '/coverage/html' );

$text = new SebastianBergmann\CodeCoverage\Report\Text( 50, 90, true, false );
echo $text->process( $cov, true );
