<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='CONNECTIVITY (NETWORKS)'>
	<meta name='author' content='Enidu Batuwanthudawe'>

	<meta name='apple-mobile-web-app-capable' content='yes' />
	<meta name='apple-mobile-web-app-status-bar-style' content='black-translucent' />

	<meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no'>

	<link rel='stylesheet' href='<?= $pptBase ?>/css/reveal.min.css'>
	<link rel='stylesheet' href='<?= $pptBase ?>/css/theme/default.css' id='theme'>
	<link rel='stylesheet' href='<?= $pptBase ?>/css/custom.css'>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

	<!-- For syntax highlighting -->
	<link rel='stylesheet' href='<?= $pptBase ?>/lib/css/zenburn.css'>

	<!-- If the query includes 'print-pdf', use the PDF print sheet -->
	<?php if (isset($_GET['print-pdf'])): ?>
	<link rel="stylesheet" href="<?= $pptBase ?>/css/print/pdf.css">
	<?php endif; ?>

		<!--[if lt IE 9]>
		<script src='<?= $pptBase ?>/lib/js/html5shiv.js'></script>
		<![endif]-->
		<script src='<?= $pptBase ?>/js/moment.min.js'></script>
	</head>

	<body>

		<div class='reveal'>
			<div class='slides'>
				<section>
					<h4>TOPIC 2.5</h4>
					<h2>INTERNET AND WEB TECHNOLOGIES </h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
 <!-- ====================================================== -->
<!-- TOPIC 2.5 : INTERNET AND WEB TECHNOLOGIES -->
<!-- ADD INSIDE <div class='slides'> -->
<!-- ====================================================== -->

<section>

<section>
	<h1>Internet and Web Technologies <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
<font color=#00FBFF>Internet and Web Technologies</font>
allow users around the world
to communicate,
share information,
and access online services.
</p>

<br>

<p>
Main Topics:
</p>

<p>
Internet
<br> 
Intranet
<br> 
Extranet
<br> 
World Wide Web
<br> 
Web Browsers
<br> 
URL
<br> 
Hyperlinks
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- INTERNET -->
<!-- ====================================================== -->

<section>

<section>
	<h1>Internet <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
The <font color=#00FBFF>Internet</font>
is a worldwide network
that connects millions of computers
and devices together.
</p>

<br>

<p>
It allows users
to communicate
and share information globally.
</p>

</section>

<section>

<h1>Definition</h1>

<p>
The Internet is a
<font color=#00FBFF>global network of networks</font>
that uses communication protocols
such as TCP/IP
to transfer data.
</p>

</section>

<section>

<h1>Uses of Internet</h1>

<p>
Communication using email and messaging
<br> 
Online learning
<br> 
Social media
<br> 
Online shopping
<br> 
Banking services
<br> 
Entertainment and streaming
<br> 
Information searching
</p> 

<svg width="650" height="260">

<circle cx="320" cy="130" r="60"
fill="#0c223f"
stroke="white"
stroke-width="4"/>

<text x="285" y="138" fill="white" font-size="26">Internet</text>

<circle cx="100" cy="60" r="28" fill="#00FBFF"/>
<circle cx="540" cy="60" r="28" fill="#FF0004"/>
<circle cx="100" cy="210" r="28" fill="#d8ff00"/>
<circle cx="540" cy="210" r="28" fill="#ffffff"/>

<line x1="130" y1="60" x2="270" y2="110"
stroke="white"
stroke-width="3"/>

<line x1="510" y1="60" x2="370" y2="110"
stroke="white"
stroke-width="3"/>

<line x1="130" y1="210" x2="270" y2="150"
stroke="white"
stroke-width="3"/>

<line x1="510" y1="210" x2="370" y2="150"
stroke="white"
stroke-width="3"/>

</svg>

</section>

</section>

<!-- ====================================================== -->
<!-- INTRANET -->
<!-- ====================================================== -->

<section>

<section>
	<h1>Intranet <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
An <font color=#00FBFF>Intranet</font>
is a private network
used inside an organisation.
</p>
 
<p>
Only authorized users
can access the intranet.
</p>

</section>

<section>

<h1>Definition</h1>

<p>
An intranet is an internal network
that uses Internet technologies
to share information
within an organisation.
</p>

</section>

<section>

<h1>Uses in Organisations</h1>

<p>
Sharing company documents
<br> 
Internal communication
<br> 
Employee management
<br> 
Accessing company resources
<br> 
Online meetings
</p>
 
<p>
Examples:
</p>

<p>
School systems
<br>
Company employee portals
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- EXTRANET -->
<!-- ====================================================== -->

<section>

<section>
	<h1>Extranet <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
An <font color=#00FBFF>Extranet</font>
is a private network
that allows limited access
to external users.
</p>

</section>

<section>

<h1>Definition</h1>

<p>
An extranet extends an intranet
by allowing controlled access
to customers,
suppliers,
or business partners.
</p>

</section>

<section>

<h1>Controlled External Access</h1>

<p>
External users can access:
</p>

<br>

<p>
Specific company information
<br> 
Shared project data
<br> 
Order systems
<br> 
Business communication tools
</p>

<br>

<p>
Access is protected
using usernames and passwords.
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- WORLD WIDE WEB -->
<!-- ====================================================== -->

<section>

<section>
	<h1>World Wide Web (WWW) <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
The <font color=#00FBFF>World Wide Web (WWW)</font>
is a collection of web pages
and websites
accessible through the Internet.
</p>

</section>

<section>

<h1>Difference Between Internet and WWW</h1>

<table style="width:100%; font-size:26px;">

<tr>
<th>Internet</th>
<th>WWW</th>
</tr>

<tr>
<td>Global network infrastructure</td>
<td>Collection of web pages</td>
</tr>

<tr>
<td>Connects devices worldwide</td>
<td>Uses the Internet</td>
</tr>

<tr>
<td>Includes email, FTP, VoIP</td>
<td>Mainly websites and webpages</td>
</tr>

<tr>
<td>Physical and logical network</td>
<td>Information service</td>
</tr>

</table>

</section>

</section>

<!-- ====================================================== -->
<!-- WEB BROWSERS -->
<!-- ====================================================== -->

<section>

<section>
	<h1>Web Browsers <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
A <font color=#00FBFF>Web Browser</font>
is a software application
used to access
and display web pages.
</p>

</section>

<section>

<h1>Functions</h1>

<p>
Access websites
<br><br>
Display web pages
<br><br>
Download files
<br><br>
Run web applications
<br><br>
Store bookmarks and history
</p>

</section>

<section>

<h1>Examples</h1>

<p>
Google Chrome
<br><br>
Mozilla Firefox
<br><br>
Microsoft Edge
<br><br>
Safari
<br><br>
Opera
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- URL -->
<!-- ====================================================== -->

<section>

<section>
	<h1>Uniform Resource Locator (URL) <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
A <font color=#00FBFF>URL</font>
is the address
used to locate resources
on the Internet.
</p>

</section>

<section>

<h1>Structure of URL</h1>

<p>
Example URL:
</p>

<br>

<p>
<font color=#d8ff00>
https://www.example.com/page.html
</font>
</p>

<br>

<p>
<font color=#00FBFF>https</font>
= protocol
</p>

<p>
<font color=#00FBFF>www.example.com</font>
= domain name
</p>

<p>
<font color=#00FBFF>/page.html</font>
= webpage/file path
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- HYPERLINKS -->
<!-- ====================================================== -->

<section>

<section>
	<h1>Hyperlinks <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
A <font color=#00FBFF>Hyperlink</font>
is a clickable text,
image,
or object
that links to another webpage,
document,
or resource.
</p>

</section>

<section>

<h1>Purpose and Uses</h1>

<p>
Navigate between web pages
<br> 
Open documents and files
<br> 
Connect related information
<br> 
Improve website navigation
<br> 
Access external resources
</p>

<br>

<svg width="620" height="220">

<rect x="70" y="70" width="180" height="80"
fill="#0c223f"
stroke="white"
stroke-width="4"/>

<text x="115" y="118" fill="white" font-size="24">Page 1</text>

<rect x="370" y="70" width="180" height="80"
fill="#0c223f"
stroke="white"
stroke-width="4"/>

<text x="415" y="118" fill="white" font-size="24">Page 2</text>

<line x1="250" y1="110" x2="370" y2="110"
stroke="#00FBFF"
stroke-width="5"/>

<polygon points="360,100 360,120 380,110"
fill="#00FBFF"/>

</svg>

</section>

</section>

<!-- ====================================================== -->
<!-- SUMMARY -->
<!-- ====================================================== -->

<section>

<h1>Summary</h1>

<p>
The Internet is a worldwide network.
<br> 
An intranet is used inside organisations.
<br> 
An extranet allows controlled external access.
<br> 
WWW contains websites and web pages.
<br> 
Web browsers display web pages.
<br> 
URLs identify resources online.
<br> 
Hyperlinks connect webpages and resources.
</p>

<br>

<h2>
<font color=#d8ff00>
END
</font>
</h2>

</section> 


			</div>
		</div>

		<script src="<?= $pptBase ?>/lib/js/head.min.js"></script>
		<script src="<?= $pptBase ?>/js/reveal.min.js"></script>

		<script>

			// Full list of configuration options available here:
			// https://github.com/hakimel/reveal.js#configuration
			Reveal.initialize({
				controls: true,
				progress: true,
				history: true,
				center: true,

				theme: Reveal.getQueryHash().theme, // available themes are in /css/theme
				transition: Reveal.getQueryHash().transition || 'default', // default/cube/page/concave/zoom/linear/fade/none

				// Parallax scrolling
				// parallaxBackgroundImage: 'https://s3.amazonaws.com/hakim-static/reveal-js/reveal-parallax-1.jpg',
				// parallaxBackgroundSize: '2100px 900px',

				// Optional libraries used to extend on reveal.js
				dependencies: [
					{ src: '<?= $pptBase ?>/lib/js/classList.js', condition: function() { return !document.body.classList; } },
					{ src: '<?= $pptBase ?>/plugin/markdown/marked.js', condition: function() { return !!document.querySelector( '[data-markdown]' ); } },
					{ src: '<?= $pptBase ?>/plugin/markdown/markdown.js', condition: function() { return !!document.querySelector( '[data-markdown]' ); } },
					{ src: '<?= $pptBase ?>/plugin/highlight/highlight.js', async: true, callback: function() { hljs.initHighlightingOnLoad(); } },
					{ src: '<?= $pptBase ?>/plugin/zoom-js/zoom.js', async: true, condition: function() { return !!document.body.classList; } },
					
				]
			});

		</script>

	</body>
</html>

