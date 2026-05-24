<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$suggerimenti       = (string) SL_Settings::get( 'suggerimenti' );
$suggerimenti_title = (string) SL_Settings::get( 'suggerimenti_title' );
$suggerimenti_icon  = (string) SL_Settings::get( 'suggerimenti_icon' );

$sl_icon_html = $suggerimenti_icon
	? '<img class="sl-tip-icon" src="' . esc_url( $suggerimenti_icon ) . '" alt="" aria-hidden="true">'
	: '<i class="biokyma xl leaf" aria-hidden="true"></i>';
?>
<section id="store" class="store-locator">
	<form action="<?php echo esc_url( home_url( '/' ) ); ?>" method="GET" role="search" class="store-form box-full" id="sl-form">
		<div>
			<div class="store-bar b p">
				<div id="store-search">
					<label for="sl-address"><?php esc_html_e( 'Indirizzo', 'store-locator' ); ?></label>
					<div class="store-input">
						<input type="text" id="sl-address" name="location"
							placeholder="<?php esc_attr_e( 'Inserisci CAP o città', 'store-locator' ); ?>"
							aria-required="false" autocomplete="off">
						<button class="cta black" type="submit"><?php esc_html_e( 'CERCA', 'store-locator' ); ?></button>
					</div>
				</div>

				<fieldset>
					<legend class="sr-only"><?php esc_html_e( 'Seleziona il raggio di ricerca', 'store-locator' ); ?></legend>
					<span class="visual-label" aria-hidden="true"><?php esc_html_e( 'Area', 'store-locator' ); ?></span>
					<div id="store-options">
						<?php foreach ( [ 25, 50, 100 ] as $i => $km ) : ?>
							<span class="store-km">
								<input type="radio" id="sl-dist-<?php echo (int) $km; ?>" name="radius" value="<?php echo (int) $km; ?>" <?php checked( $km, 25 ); ?>>
								<label for="sl-dist-<?php echo (int) $km; ?>"><?php echo (int) $km; ?> KM</label>
							</span>
						<?php endforeach; ?>
					</div>
				</fieldset>
			</div>
		</div>
	</form>

	<figure class="media">
		<div id="store-map" role="application" aria-label="<?php esc_attr_e( 'Mappa dei punti vendita', 'store-locator' ); ?>"></div>
		<figcaption class="sr-only"><?php esc_html_e( 'Mappa interattiva dei punti vendita Biokyma.', 'store-locator' ); ?></figcaption>
	</figure>

	<section class="category box-full slider pb mnt" role="region"
		aria-label="<?php esc_attr_e( 'Lista negozi presenti nell\'area interessata', 'store-locator' ); ?>">

		<div class="slide-intro d-mid-none my">
			<p class="h3"><?php echo $sl_icon_html; // safe: built above with esc_url ?><?php echo esc_html( $suggerimenti_title ); ?></p>
			<p class="leaf"><?php echo esc_html( $suggerimenti ); ?></p>
		</div>

		<div class="slide-box">
			<div class="slide-wrap" id="sl-slides">
				<article class="slide cat-slide slide-intro" role="group" aria-roledescription="slide">
					<p class="h3"><?php echo $sl_icon_html; // safe: built above with esc_url ?><?php echo esc_html( $suggerimenti_title ); ?></p>
					<p class="leaf"><?php echo esc_html( $suggerimenti ); ?></p>
				</article>
				<article class="slide cat-slide sl-loading" role="group" aria-roledescription="slide">
					<div class="store-card">
						<div class="slide-txt">
							<small>&nbsp;</small>
							<h3><?php esc_html_e( 'Caricamento in corso…', 'store-locator' ); ?></h3>
						</div>
					</div>
				</article>
			</div>

			<div class="slide-btns d-sm-none">
				<button class="aft" type="button" id="sl-prev" aria-label="<?php esc_attr_e( 'Slide precedente', 'store-locator' ); ?>">
					<span class="cta border" aria-hidden="true"><i class="sx-arrow"></i></span>
				</button>
				<button class="fore" type="button" id="sl-next" aria-label="<?php esc_attr_e( 'Slide successiva', 'store-locator' ); ?>">
					<span class="cta border" aria-hidden="true"><i class="arrow"></i></span>
				</button>
			</div>

			<figure class="dots d-mid-none" id="sl-dots" aria-hidden="true"></figure>
		</div>

		<p class="sl-empty" id="sl-empty" hidden></p>
	</section>
</section>
