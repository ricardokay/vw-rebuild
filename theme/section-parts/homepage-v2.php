<?php
/**
 * Homepage v2 — static clone of the reference mockup.
 * PHASE 1: hardcoded content matching the mockup exactly. Verify visual
 * fidelity first; only then swap to data-driven content (phase 2), keeping
 * this markup/CSS unchanged. Fresh .vwh2- scope — see homepage-v2.css.
 */
?>

<div class="vwh2-container">

	<!-- Masthead -->
	<div class="vwh2-masthead">
		<div class="vwh2-masthead__dateline">
			<span>Saturday, July 25, 2026 · Vancouver, BC</span>
			<span>No Ads · No Clickbait · Independent Since 2006</span>
		</div>
		<a href="#" class="vwh2-masthead__logo-link">
			<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/logo_VW_wordmark.png' ); ?>" alt="Vancouver Weekly" class="vwh2-masthead__logo">
		</a>
		<p class="vwh2-masthead__motto">The Record of the City's Culture — Twenty Years and Counting</p>
		<nav class="vwh2-masthead__nav">
			<a href="#" class="vwh2-masthead__nav-item"><span class="vwh2-mark vwh2-mark--music"></span>A La Music</a>
			<a href="#" class="vwh2-masthead__nav-item"><span class="vwh2-mark vwh2-mark--photo"></span>Photography</a>
			<a href="#" class="vwh2-masthead__nav-item"><span class="vwh2-mark vwh2-mark--food"></span>Food &amp; Drink</a>
			<a href="#" class="vwh2-masthead__nav-item"><span class="vwh2-mark vwh2-mark--outabout"></span>Out N About</a>
			<a href="#" class="vwh2-masthead__nav-item"><span class="vwh2-mark vwh2-mark--political"></span>Political Megaphone</a>
			<a href="#" class="vwh2-masthead__nav-item"><span class="vwh2-mark vwh2-mark--books"></span>Book Reviews</a>
		</nav>
	</div>
	<hr class="vwh2-rule vwh2-rule--heavy">

	<!-- Lead -->
	<section class="vwh2-lead">
		<div class="vwh2-lead__text">
			<span class="vwh2-kicker"><span class="vwh2-mark vwh2-mark--outabout"></span>Out N About</span>
			<h1 class="vwh2-lead__hed"><a href="#">Who Gets to Save the Rio This Time?</a></h1>
			<p class="vwh2-lead__dek">East Van's stubborn single-screen has outlived two chains, three condo proposals, and a pandemic. Now its future hangs on a lease clause nobody read until March — and on who shows up next Thursday.</p>
			<span class="vwh2-byline">By <strong>Naomi Cheung</strong> · Photography by Tomás Rivera · 18 min read</span>
		</div>
		<div class="vwh2-lead__img-col">
			<img src="<?php echo esc_url( content_url( '/uploads/2026/07/646612242114725.jpg' ) ); ?>" alt="" class="vwh2-lead__img">
			<span class="vwh2-lead__img-credit">Photo by Gael D on Unsplash</span>
		</div>
	</section>
	<p class="vwh2-lead__caption">The Rio's marquee, July 2026. Its lease expires in November. TOMÁS RIVERA</p>
	<hr class="vwh2-rule">

	<!-- This Week -->
	<div class="vwh2-thisweek">
		<span class="vwh2-thisweek__label">This Week →</span>
		<div class="vwh2-thisweek__item"><span class="vwh2-thisweek__day">Fri</span>Khatsahlano stretches ten blocks of West 4th, rain or not</div>
		<div class="vwh2-thisweek__item"><span class="vwh2-thisweek__day">Sat</span>Queer Arts Festival closes with an all-night print sale</div>
		<div class="vwh2-thisweek__item"><span class="vwh2-thisweek__day">Sun</span>Powell Street Festival's last day at Oppenheimer Park</div>
	</div>
	<hr class="vwh2-rule">

	<!-- A La Music -->
	<div class="vwh2-zonehead">
		<span class="vwh2-mark vwh2-mark--music"></span>
		<span class="vwh2-zonehead__title">A La Music</span>
		<a href="#" class="vwh2-more">All Music →</a>
	</div>
	<div class="vwh2-music">
		<img src="https://images.unsplash.com/photo-1493676304819-0d7a8d026dcf?w=800&q=80" alt="" class="vwh2-music__img">
		<div>
			<h3 class="vwh2-music__hed"><a href="#">Dan Bejar Never Left the Neighbourhood</a></h3>
			<p class="vwh2-music__dek">Destroyer plays two nights at the Hollywood, ten blocks from where half of these songs were written. We walked the ten blocks with him.</p>
			<span class="vwh2-byline">By <strong>Dev Bhandari</strong></span>
		</div>
		<div class="vwh2-music__rule"></div>
		<div class="vwh2-music__stack">
			<div>
				<a class="vwh2-music__stack-hed" href="#">Mint Records Turns 35 With a Two-Night Blowout at the Fox</a>
				<span class="vwh2-byline">By <strong>Jules Ohanessian</strong></span>
			</div>
			<div>
				<a class="vwh2-music__stack-hed" href="#">The Year Vancouver Shoegaze Got Loud Again</a>
				<span class="vwh2-byline">By <strong>Dev Bhandari</strong></span>
			</div>
		</div>
	</div>
	<hr class="vwh2-rule">

</div>

<!-- Photography — full-bleed background only; content aligns to the shared container -->
<section class="vwh2-photo">
	<div class="vwh2-container">
		<div class="vwh2-photo__head">
			<span class="vwh2-mark vwh2-mark--photo"></span>Photography
			<a href="#" class="vwh2-more">All Photo Essays →</a>
		</div>
		<div class="vwh2-photo__inner">
			<img src="https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=1200&q=80" alt="" class="vwh2-photo__img">
			<div class="vwh2-photo__text">
				<span class="vwh2-photo__eyebrow">Photo Essay</span>
				<h2 class="vwh2-photo__hed"><a href="#">Pit Notes: 36 Frames From Levitation Vancouver</a></h2>
				<p class="vwh2-photo__dek">No flash, no pass restrictions, no retouching. Three nights in the photo pit, printed as shot.</p>
				<span class="vwh2-photo__byline">Photographs by <strong>Tomás Rivera</strong></span>
				<div class="vwh2-photo__sub">
					<a class="vwh2-photo__sub-hed" href="#">Craft: Shooting the Commodore's Terrible, Beautiful Red Light</a>
					<span class="vwh2-photo__byline">By <strong>Hana Yoshida</strong></span>
				</div>
			</div>
		</div>
		<div class="vwh2-photo__strip">
			<a href="#" class="vwh2-photo__thumb"><img src="https://images.unsplash.com/photo-1524368535928-5b5e00ddc76b?w=500&q=80" alt=""></a>
			<a href="#" class="vwh2-photo__thumb"><img src="https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=500&q=80" alt=""></a>
			<a href="#" class="vwh2-photo__thumb"><img src="https://images.unsplash.com/photo-1499364615650-ec38552f4f34?w=500&q=80" alt=""></a>
			<a href="#" class="vwh2-photo__thumb"><img src="https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=500&q=80" alt=""></a>
		</div>
	</div>
</section>

<div class="vwh2-container">

	<!-- Tri-column: Food / Political / Books -->
	<div class="vwh2-tri">
		<div>
			<div class="vwh2-tri__col-hed"><span class="vwh2-mark vwh2-mark--food"></span>Food &amp; Drink<a href="#" class="vwh2-more">All Food &amp; Drink →</a></div>
			<img src="https://images.unsplash.com/photo-1466978913421-dad2ebd01d17?w=700&q=80" alt="" class="vwh2-tri__img">
			<a class="vwh2-tri__hed" href="#">The $9 Lunch Is Alive on Kingsway</a>
			<p class="vwh2-tri__dek">You just have to know which awnings to trust. Eleven stops, no misses.</p>
			<span class="vwh2-byline">By <strong>Katie Sawatzky</strong></span>
			<div class="vwh2-tri__sub">
				<span class="vwh2-tri__sub-eyebrow">Review</span>
				<a class="vwh2-tri__hed vwh2-tri__hed--sub" href="#">Oyster Express's Second Act Is Better Than Its First</a>
				<span class="vwh2-byline">By <strong>Ray Fillion</strong></span>
			</div>
		</div>
		<div class="vwh2-tri__rule"></div>
		<div>
			<div class="vwh2-tri__col-hed"><span class="vwh2-mark vwh2-mark--political"></span>Political Megaphone<a href="#" class="vwh2-more">All Political →</a></div>
			<p class="vwh2-tri__quote">"Every city council since 2008 has promised to fix the empty-storefront problem. Here is the tax none of them will say out loud."</p>
			<a class="vwh2-tri__hed" href="#">The Vacancy Tax Vancouver Keeps Refusing to Try</a>
			<p class="vwh2-tri__dek">An argument in four storefronts, two of them on the same block as our old office.</p>
			<span class="vwh2-byline">By <strong>Marcus Oduya</strong> · Opinion</span>
		</div>
		<div class="vwh2-tri__rule"></div>
		<div>
			<div class="vwh2-tri__col-hed"><span class="vwh2-mark vwh2-mark--books"></span>Book Reviews<a href="#" class="vwh2-more">All Books →</a></div>
			<img src="https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=500&q=80" alt="" class="vwh2-tri__img vwh2-tri__img--sm">
			<a class="vwh2-tri__hed" href="#">'False Creek' Is the Flood Novel Vancouver Deserves</a>
			<p class="vwh2-tri__dek">Sam Wiebe's latest drowns the city slowly, and lovingly.</p>
			<span class="vwh2-byline">By <strong>Priya Rathod</strong></span>
		</div>
	</div>
	<hr class="vwh2-rule">

	<!-- Archive closer -->
	<div class="vwh2-archive">
		<div class="vwh2-archive__inner">
			<div>
				<span class="vwh2-archive__eyebrow">From the Archive</span>
				<h2 class="vwh2-archive__hed">20 years,<br>16,412 stories.</h2>
				<p class="vwh2-archive__line">Every issue since 2006, rebuilt and readable. The record of the city's culture doesn't expire.</p>
				<a href="#" class="vwh2-archive__cta">Browse the Archive →</a>
			</div>
			<a href="#" class="vwh2-archive__issue">
				<div class="vwh2-archive__issue-top">
					<span>This Week in <strong>2009</strong></span>
					<span>Issue No. 183 · July 23, 2009</span>
				</div>
				<span class="vwh2-archive__issue-hed">Our First (Skeptical) Review of the Biltmore's Reinvention</span>
				<p class="vwh2-archive__issue-quote">"A cabaret licence, a new sound guy, and a lot of promises. We give it a year." It's still there. We were wrong, happily.</p>
				<span class="vwh2-archive__issue-byline">By the desk of A La Music, 2009 · Re-published as printed</span>
			</a>
		</div>
	</div>

</div>

<!-- Footer -->
<div class="vwh2-container">
	<div class="vwh2-footer">
		<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/logo_VW_wordmark.png' ); ?>" alt="Vancouver Weekly" class="vwh2-footer__logo">
		<nav class="vwh2-footer__nav">
			<a href="#">A La Music</a>
			<a href="#">Photography</a>
			<a href="#">Food &amp; Drink</a>
			<a href="#">Out N About</a>
			<a href="#">Political Megaphone</a>
			<a href="#">Book Reviews</a>
		</nav>
		<span class="vwh2-footer__tag">Independent Since 2006</span>
	</div>
</div>
