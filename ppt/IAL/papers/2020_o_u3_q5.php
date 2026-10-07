<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(dirname(__DIR__, 2))), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='papers'>
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
					<h4>2020 Oct</h4>
					<h2> Unit 3</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>   
    <!-- 5(a) -->
    <section>
        <section>
            <h4><b>5(a)</b> Explain how an active data dictionary differs from a passive data dictionary when the database is running. (3 marks)</h4>
        </section>

        <section>
            <p><b>Answer:</b></p>

            <p>An active data dictionary is part of the database system and is automatically updated whenever changes are made to the database.</p>
  </section> 
	<section> 
            <p>The DBMS checks and uses it while the database is running.</p>
  </section> 
	<section> 
            <p>A passive data dictionary is stored separately from the database and is updated manually or at intervals rather than automatically.</p>
  </section> 
	<section> 
            <p>As a result, an active data dictionary is always consistent with the database, while a passive data dictionary may become outdated or inaccurate.</p>
        </section>
    </section>

    <!-- 5(b) -->
    <section>
        <section>
            <h4><b>5(b)</b> Describe two other functions of a data dictionary. (4 marks)</h4>
        </section>

        <section>
            <p><b>Answer:</b></p>

            <p><b>Function 1:</b></p>

            <p>A data dictionary controls user access rights and permissions.</p>

            <p>This ensures that only authorised users can view or edit certain parts of the database, improving security.</p>
  </section> 
	<section> 
            <p><b>Function 2:</b></p>

            <p>A data dictionary can generate reports about database resources and data usage.</p>

            <p>This helps database administrators monitor and manage the database more effectively.</p>
        </section>
    </section>

    <!-- 5(c) -->
    <section>
        <section>
            <h4><b>5(c)</b> Create a data dictionary for the contacts part of the database. (9 marks)</h4>
        </section>

        <section>
            <p><b>Answer:</b></p>

            <table border="1" cellspacing="0" cellpadding="8">

                <tr>
                    <th>Table Name: Tbl_Contact</th>
                    <th>Data Type</th>
                    <th>Key (P/F)</th>
                    <th>Required (Y/N)</th>
                    <th>Field Size</th>
                    <th>Description / Notes</th>
                </tr>

                <tr>
                    <td>Contact_ID</td>
                    <td>Integer</td>
                    <td>PK</td>
                    <td>Y</td>
                    <td>6</td>
                    <td>Unique contact ID</td>
                </tr>

                <tr>
                    <td>First_Name</td>
                    <td>Text</td>
                    <td>-</td>
                    <td>Y</td>
                    <td>50</td>
                    <td>Contact first name</td>
                </tr>

                <tr>
                    <td>Last_Name</td>
                    <td>Text</td>
                    <td>-</td>
                    <td>Y</td>
                    <td>50</td>
                    <td>Contact surname</td>
                </tr>

                <tr>
                    <td>Date_Of_Birth</td>
                    <td>Date</td>
                    <td>-</td>
                    <td>N</td>
                    <td>10</td>
                    <td>Format DD/MM/YYYY. Validate age >18</td>
                </tr>

                <tr>
                    <td>Marital_Status</td>
                    <td>Text</td>
                    <td>-</td>
                    <td>N</td>
                    <td>10</td>
                    <td>List: Married, Single, Divorced, Widowed</td>
                </tr>

                <tr>
                    <td>Telephone</td>
                    <td>Text</td>
                    <td>-</td>
                    <td>Y</td>
                    <td>12</td>
                    <td>Contact number</td>
                </tr>

                <tr>
                    <td>Employer</td>
                    <td>Text</td>
                    <td>-</td>
                    <td>N</td>
                    <td>50</td>
                    <td>Employer name</td>
                </tr>

            </table>

             </section> 
	<section> 

            <table border="1" cellspacing="0" cellpadding="8">

                <tr>
                    <th>Table Name: Tbl_Address</th>
                    <th>Data Type</th>
                    <th>Key (P/F)</th>
                    <th>Required (Y/N)</th>
                    <th>Field Size</th>
                    <th>Description / Notes</th>
                </tr>

                <tr>
                    <td>Address_ID</td>
                    <td>Integer</td>
                    <td>PK</td>
                    <td>Y</td>
                    <td>6</td>
                    <td>Unique address ID</td>
                </tr>

                <tr>
                    <td>Contact_ID</td>
                    <td>Integer</td>
                    <td>FK</td>
                    <td>Y</td>
                    <td>6</td>
                    <td>Links to Tbl_Contact</td>
                </tr>

                <tr>
                    <td>Address_Type</td>
                    <td>Text</td>
                    <td>-</td>
                    <td>Y</td>
                    <td>15</td>
                    <td>Home/Work etc.</td>
                </tr>

                <tr>
                    <td>Address_Line1</td>
                    <td>Text</td>
                    <td>-</td>
                    <td>Y</td>
                    <td>50</td>
                    <td>Main address line</td>
                </tr>

                <tr>
                    <td>Address_Line2</td>
                    <td>Text</td>
                    <td>-</td>
                    <td>N</td>
                    <td>50</td>
                    <td>Additional address</td>
                </tr>

                <tr>
                    <td>Town</td>
                    <td>Text</td>
                    <td>-</td>
                    <td>N</td>
                    <td>25</td>
                    <td>Town name</td>
                </tr>

                <tr>
                    <td>Postcode</td>
                    <td>Text</td>
                    <td>-</td>
                    <td>Y</td>
                    <td>10</td>
                    <td>Postal code</td>
                </tr>

            </table>
        </section>
    </section> 
	<section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2020_o_u3_q6.php">next</a></p>
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

