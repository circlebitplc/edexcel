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
					<h4>2022 May</h4>
					<h2> Unit 3</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>   
 <!-- ====================================================== -->
<!-- 5 -->
<!-- ====================================================== -->

<section>

    <section>
        <h4><b>5</b> A store sells swimming items and wants to convert spreadsheet data into a database in Third Normal Form (3NF). (12 marks)</h4>
    </section>
 <section>
       

<img src='<?= $dirBase ?>/2023_m_u3_q5.png' height=500px>

    </section>
    <!-- ====================================================== -->
    <!-- Step 1 -->
    <!-- ====================================================== -->

    <section>
        <p><b>Step 1 — Understand the Problem</b></p>

        <p>
            The spreadsheet currently stores:
        </p>

        <ul>
            <li>Order details</li>
            <li>Customer names</li>
            <li>Stock items</li>
            <li>Manufacturers</li>
        </ul>

        <p>
            all in one table.
        </p>
    </section>

    <section>
        <p>
            This causes:
        </p>

        <ul>
            <li>Duplicated data</li>
            <li>Update anomalies</li>
            <li>Inefficient storage</li>
            <li>Poor scalability</li>
        </ul>

        <p>
            To solve this,
            the database must be normalised
            into Third Normal Form (3NF).
        </p>
    </section>

    <!-- ====================================================== -->
    <!-- What is 3NF -->
    <!-- ====================================================== -->

    <section>
        <p><b>What is Third Normal Form (3NF)?</b></p>

        <p>
            A database is in 3NF when:
        </p>

        <ul>
            <li>Each field contains atomic values only</li>
            <li>No repeating groups exist</li>
            <li>All non-key fields depend only on the primary key</li>
            <li>No transitive dependencies exist</li>
        </ul>

        <p>
            This reduces redundancy
            and improves data integrity.
        </p>
    </section>

    <!-- ====================================================== -->
    <!-- tbl_customer -->
    <!-- ====================================================== -->

    <section>
        <p><b>Table 1 — tbl_customer</b></p>

        <table border="1" cellspacing="0" cellpadding="8">

            <tr>
                <th>Field Name</th>
            </tr>

            <tr>
                <td>customer_id</td>
            </tr>

            <tr>
                <td>customer_name</td>
            </tr>

        </table>
    </section>

    <section>
        <p><b>Explanation:</b></p>

        <p>
            This table stores customer details separately
            so customer names are not repeated
            in every order.
        </p>

        <p><b>Primary Key:</b></p>

        <p>
            customer_id
        </p>
    </section>

    <!-- ====================================================== -->
    <!-- tbl_manufacturer -->
    <!-- ====================================================== -->

    <section>
        <p><b>Table 2 — tbl_manufacturer</b></p>

        <table border="1" cellspacing="0" cellpadding="8">

            <tr>
                <th>Field Name</th>
            </tr>

            <tr>
                <td>mfg_id</td>
            </tr>

            <tr>
                <td>mfg_name</td>
            </tr>

        </table>
    </section>

    <section>
        <p><b>Explanation:</b></p>

        <p>
            Manufacturers are stored separately
            because many products
            may come from the same manufacturer.
        </p>

        <p><b>Primary Key:</b></p>

        <p>
            mfg_id
        </p>
    </section>

    <!-- ====================================================== -->
    <!-- tbl_stock -->
    <!-- ====================================================== -->

    <section>
        <p><b>Table 3 — tbl_stock</b></p>

        <table border="1" cellspacing="0" cellpadding="8">

            <tr>
                <th>Field Name</th>
            </tr>

            <tr>
                <td>stock_id</td>
            </tr>

            <tr>
                <td>stock_name</td>
            </tr>

            <tr>
                <td>mfg_id*</td>
            </tr>

        </table>
    </section>

    <section>
        <p><b>Explanation:</b></p>

        <p>
            This table stores information
            about products sold by the shop.
        </p>

        <p><b>Foreign Key:</b></p>

        <p>
            mfg_id* links to tbl_manufacturer
        </p>

        <p><b>Benefits:</b></p>

        <ul>
            <li>Avoids repeatedly storing manufacturer names</li>
            <li>Simplifies updates</li>
        </ul>
    </section>

    <!-- ====================================================== -->
    <!-- tbl_order -->
    <!-- ====================================================== -->

    <section>
        <p><b>Table 4 — tbl_order</b></p>

        <table border="1" cellspacing="0" cellpadding="8">

            <tr>
                <th>Field Name</th>
            </tr>

            <tr>
                <td>order_id</td>
            </tr>

            <tr>
                <td>date</td>
            </tr>

            <tr>
                <td>customer_id*</td>
            </tr>

        </table>
    </section>

    <section>
        <p><b>Explanation:</b></p>

        <p>
            Stores information
            about each customer order.
        </p>

        <p><b>Foreign Key:</b></p>

        <p>
            customer_id* links to tbl_customer
        </p>

        <p><b>Benefits:</b></p>

        <ul>
            <li>One customer can have many orders</li>
            <li>Customer data is not duplicated</li>
        </ul>
    </section>

    <!-- ====================================================== -->
    <!-- tbl_item -->
    <!-- ====================================================== -->

    <section>
        <p><b>Table 5 — tbl_item</b></p>

        <table border="1" cellspacing="0" cellpadding="8">

            <tr>
                <th>Field Name</th>
            </tr>

            <tr>
                <td>order_id*</td>
            </tr>

            <tr>
                <td>stock_id*</td>
            </tr>

            <tr>
                <td>quantity</td>
            </tr>

        </table>
    </section>

    <section>
        <p><b>Explanation:</b></p>

        <p>
            This junction table links orders
            to stock items.
        </p>

        <p>
            One order may contain many products,
            and one product may appear in many orders.
        </p>
    </section>

    <section>
        <p><b>Composite Primary Key:</b></p>

        <p>
            order_id* + stock_id*
        </p>

        <p><b>Foreign Keys:</b></p>

        <ul>
            <li>order_id* links to tbl_order</li>
            <li>stock_id* links to tbl_stock</li>
        </ul>
    </section>

    <!-- ====================================================== -->
    <!-- Final Structure -->
    <!-- ====================================================== -->

   

    <!-- ====================================================== -->
    <!-- Why 3NF -->
    <!-- ====================================================== -->

    <section>
        <p><b>Why This Database is in 3NF</b></p>

        <p><b>1. No Repeating Groups</b></p>

        <p>
            Products are separated
            into their own tables.
        </p>
    </section>

    <section>
        <p><b>2. No Partial Dependencies</b></p>

        <p>
            All non-key fields depend
            on the whole primary key.
        </p>
    </section>

    <section>
        <p><b>3. No Transitive Dependencies</b></p>

        <p>
            Manufacturer details are stored separately
            from stock items.
        </p>
    </section>

    <!-- ====================================================== -->
    <!-- Benefits -->
    <!-- ====================================================== -->

    <section>
        <p><b>Benefits of This Design</b></p>

        <p><b>Reduced Redundancy</b></p>

        <p>
            Manufacturer and customer names
            are not repeated unnecessarily.
        </p>
    </section>

    <section>
        <p><b>Improved Data Integrity</b></p>

        <p>
            Changes only need to occur once.
        </p>

        <p><b>Example:</b></p>

        <p>
            If a manufacturer changes name,
            only one record is updated.
        </p>
    </section>

    <section>
        <p><b>Easier Maintenance</b></p>

        <p>
            The database becomes:
        </p>

        <ul>
            <li>Scalable</li>
            <li>Organised</li>
            <li>Easier to query</li>
        </ul>
    </section>

    <section>
        <p><b>Better Performance</b></p>

        <p>
            Smaller tables improve efficiency.
        </p>
    </section>

    <!-- ====================================================== -->
    <!-- Conclusion -->
    <!-- ====================================================== -->

    <section>
        <p><b>Conclusion</b></p>

        <p>
            Converting the spreadsheet
            into a fully normalised relational database
            improves:
        </p>

        <ul>
            <li>Storage efficiency</li>
            <li>Consistency</li>
            <li>Scalability</li>
            <li>Reliability</li>
        </ul>

        <p>
            This structure follows
            Third Normal Form correctly.
        </p>
    </section>

</section>
 
   <section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2023_m_u3_q6.php">next</a></p>
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

