<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(dirname(__DIR__))), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='Topic 12 MCQ – Manipulating data'>
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
					<h4>Topic 12 MCQ</h4>
					<h2> Manipulating data</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

 <section> 
	<section>
		<h4><b>1)</b> Data integrity refers to:</h4>
				<p>A. The speed of data transfer</p>
				<p>B. The reliability and accuracy of data</p>
				<p>C. The size of a database</p>
				<p>D. The encryption of data</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>2)</b> Data governance is concerned with:</h4>
				<p>A. Managing how data is stored and used responsibly</p>
				<p>B. Increasing storage size</p>
				<p>C. Deleting duplicate files</p>
				<p>D. Compressing files</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>3)</b> Which of the following helps maintain data integrity?</h4>
				<p>A. Ignoring errors</p>
				<p>B. Data validation</p>
				<p>C. Deleting backups</p>
				<p>D. Removing constraints</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>4)</b> A data dictionary is:</h4>
				<p>A. A backup file</p>
				<p>B. A blueprint of database structure</p>
				<p>C. A firewall tool</p>
				<p>D. A spreadsheet</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>5)</b> Metadata is best described as:</h4>
				<p>A. Large data files</p>
				<p>B. Data about data</p>
				<p>C. Duplicate data</p>
				<p>D. Deleted data</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>6)</b> Which of the following is included in a data dictionary?</h4>
				<p>A. Field name</p>
				<p>B. Field type</p>
				<p>C. Field length</p>
				<p>D. All of the above</p>
	</section> 
	<section><p><b>Answer: D</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>7)</b> The SQL data type INT stores:</h4>
				<p>A. Text</p>
				<p>B. Whole numbers</p>
				<p>C. Dates</p>
				<p>D. Images</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>8)</b> VARCHAR is mainly used for:</h4>
				<p>A. Boolean values</p>
				<p>B. Fixed-length integers</p>
				<p>C. Variable-length text</p>
				<p>D. Floating numbers</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>9)</b> BOOLEAN stores:</h4>
				<p>A. TRUE or FALSE</p>
				<p>B. Dates</p>
				<p>C. Currency</p>
				<p>D. Images</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>10)</b> DATETIME stores:</h4>
				<p>A. Text values</p>
				<p>B. Boolean values</p>
				<p>C. Date and time</p>
				<p>D. Whole numbers</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>
<section> 
	<section>
		<h4><b>11)</b> The presence check ensures:</h4>
				<p>A. Data is within range</p>
				<p>B. Data is entered</p>
				<p>C. Data is formatted correctly</p>
				<p>D. Data is duplicated</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>12)</b> A range check ensures:</h4>
				<p>A. Data exists</p>
				<p>B. Data is unique</p>
				<p>C. Data falls within acceptable limits</p>
				<p>D. Data is encrypted</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>13)</b> A lookup check compares data with:</h4>
				<p>A. Random values</p>
				<p>B. Predefined list or table</p>
				<p>C. Encryption keys</p>
				<p>D. Backup copies</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>14)</b> A length check ensures:</h4>
				<p>A. Correct data format</p>
				<p>B. Data is not duplicated</p>
				<p>C. Input has correct number of characters</p>
				<p>D. Data is secure</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>15)</b> A format check verifies:</h4>
				<p>A. Correct data pattern</p>
				<p>B. Data is stored twice</p>
				<p>C. Data speed</p>
				<p>D. Database size</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>16)</b> A check digit is mainly used to:</h4>
				<p>A. Increase storage</p>
				<p>B. Detect input errors</p>
				<p>C. Encrypt data</p>
				<p>D. Delete duplicates</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>17)</b> Data redundancy means:</h4>
				<p>A. Missing data</p>
				<p>B. Duplicate unnecessary data</p>
				<p>C. Encrypted data</p>
				<p>D. Validated data</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>18)</b> One problem of data redundancy is:</h4>
				<p>A. Reduced storage cost</p>
				<p>B. Data inconsistency</p>
				<p>C. Faster queries</p>
				<p>D. Increased integrity</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>19)</b> Normalisation is used to:</h4>
				<p>A. Encrypt data</p>
				<p>B. Reduce redundancy</p>
				<p>C. Increase storage</p>
				<p>D. Delete backups</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>20)</b> First Normal Form (1NF) requires:</h4>
				<p>A. No primary key</p>
				<p>B. Atomic values</p>
				<p>C. Duplicate records</p>
				<p>D. No constraints</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>
<section> 
	<section>
		<h4><b>21)</b> Second Normal Form (2NF) requires:</h4>
				<p>A. Partial dependencies removed</p>
				<p>B. No atomic values</p>
				<p>C. Duplicate columns</p>
				<p>D. No keys</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>22)</b> Third Normal Form (3NF) requires:</h4>
				<p>A. No transitive dependencies</p>
				<p>B. Duplicate keys</p>
				<p>C. All fields nullable</p>
				<p>D. No foreign keys</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>23)</b> A primary key:</h4>
				<p>A. Allows duplicates</p>
				<p>B. Uniquely identifies a record</p>
				<p>C. Stores images</p>
				<p>D. Encrypts data</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>24)</b> A foreign key:</h4>
				<p>A. Is a duplicate key</p>
				<p>B. Links tables together</p>
				<p>C. Stores text only</p>
				<p>D. Deletes records</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>25)</b> A relational database:</h4>
				<p>A. Stores unrelated files</p>
				<p>B. Uses linked tables</p>
				<p>C. Is only for text</p>
				<p>D. Avoids keys</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>26)</b> An entity in a database represents:</h4>
				<p>A. A relationship</p>
				<p>B. A real-world object</p>
				<p>C. A constraint</p>
				<p>D. A data type</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>27)</b> An attribute is:</h4>
				<p>A. A database table</p>
				<p>B. A property of an entity</p>
				<p>C. A validation rule</p>
				<p>D. A backup file</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>28)</b> One-to-many relationship means:</h4>
				<p>A. One record links to multiple records</p>
				<p>B. Multiple records link to one</p>
				<p>C. No relationship</p>
				<p>D. Only one table</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>29)</b> In ER diagrams, entities are shown as:</h4>
				<p>A. Circles</p>
				<p>B. Rectangles</p>
				<p>C. Lines</p>
				<p>D. Arrows</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>30)</b> A unique identifier ensures:</h4>
				<p>A. Duplicate records</p>
				<p>B. Unique records</p>
				<p>C. Data redundancy</p>
				<p>D. Data deletion</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>
<section> 
	<section>
		<h4><b>31)</b> Big Data refers to:</h4>
				<p>A. Small datasets</p>
				<p>B. Structured spreadsheets only</p>
				<p>C. Extremely large and complex datasets</p>
				<p>D. Temporary files</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>32)</b> Volume in Big Data refers to:</h4>
				<p>A. Speed</p>
				<p>B. Amount of data</p>
				<p>C. Accuracy</p>
				<p>D. Encryption</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>33)</b> Velocity refers to:</h4>
				<p>A. Data size</p>
				<p>B. Speed of data generation</p>
				<p>C. Storage format</p>
				<p>D. Backup frequency</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>34)</b> Variety refers to:</h4>
				<p>A. Different data types</p>
				<p>B. Data duplication</p>
				<p>C. Data encryption</p>
				<p>D. Network speed</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>35)</b> Veracity refers to:</h4>
				<p>A. Data reliability</p>
				<p>B. Data speed</p>
				<p>C. Data format</p>
				<p>D. Data size</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>36)</b> Value in Big Data refers to:</h4>
				<p>A. Cost of storage</p>
				<p>B. Usefulness of data</p>
				<p>C. Data duplication</p>
				<p>D. Network bandwidth</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>37)</b> Data analytics is used to:</h4>
				<p>A. Delete data</p>
				<p>B. Analyse patterns and trends</p>
				<p>C. Reduce storage</p>
				<p>D. Encrypt databases</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>38)</b> A constraint in SQL:</h4>
				<p>A. Prevents invalid data</p>
				<p>B. Deletes tables</p>
				<p>C. Speeds up CPU</p>
				<p>D. Encrypts fields</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>39)</b> Audit trails are used to:</h4>
				<p>A. Increase storage</p>
				<p>B. Track data changes</p>
				<p>C. Delete records</p>
				<p>D. Duplicate data</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>40)</b> Which practice directly improves reliability of stored data?</h4>
				<p>A. Data encryption</p>
				<p>B. Data validation before storage</p>
				<p>C. Increasing storage size</p>
				<p>D. Removing constraints</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>
 <section> 
	<section>
		<h4><b>41)</b> Which element would NOT normally appear in a data dictionary?</h4>
				<p>A. Field name</p>
				<p>B. Data type</p>
				<p>C. Field description</p>
				<p>D. User password</p>
	</section> 
	<section><p><b>Answer: D</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>42)</b> The main purpose of metadata is to:</h4>
				<p>A. Store backup files</p>
				<p>B. Describe characteristics of data</p>
				<p>C. Replace primary keys</p>
				<p>D. Encrypt information</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>43)</b> Which SQL datatype would be most suitable for storing prices?</h4>
				<p>A. VARCHAR</p>
				<p>B. BOOLEAN</p>
				<p>C. DECIMAL</p>
				<p>D. CHAR</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>44)</b> Which SQL datatype would best store a postcode?</h4>
				<p>A. INT</p>
				<p>B. DECIMAL</p>
				<p>C. VARCHAR</p>
				<p>D. FLOAT</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>45)</b> A FLOAT datatype is most appropriate for storing:</h4>
				<p>A. Whole numbers only</p>
				<p>B. Decimal numbers</p>
				<p>C. Boolean values</p>
				<p>D. Text values</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>46)</b> Which datatype stores large binary files such as videos?</h4>
				<p>A. JSON</p>
				<p>B. VARBINARY</p>
				<p>C. BOOLEAN</p>
				<p>D. INT</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>47)</b> A list validation check is most suitable for:</h4>
				<p>A. Age range</p>
				<p>B. Gender selection</p>
				<p>C. Email format</p>
				<p>D. Password length</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>48)</b> Which validation rule ensures an email contains “@”?</h4>
				<p>A. Range check</p>
				<p>B. Format check</p>
				<p>C. Lookup check</p>
				<p>D. Presence check</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>49)</b> If a salary must be between £10,000 and £100,000, which validation is required?</h4>
				<p>A. Presence</p>
				<p>B. Format</p>
				<p>C. Range</p>
				<p>D. Lookup</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>50)</b> A lookup validation reduces errors by:</h4>
				<p>A. Encrypting input</p>
				<p>B. Comparing against predefined data</p>
				<p>C. Increasing length</p>
				<p>D. Creating duplicates</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>
<section> 
	<section>
		<h4><b>51)</b> A check digit is commonly used in:</h4>
				<p>A. Password validation</p>
				<p>B. ISBN numbers</p>
				<p>C. Email addresses</p>
				<p>D. Usernames</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>52)</b> Which issue is caused by data redundancy?</h4>
				<p>A. Improved consistency</p>
				<p>B. Reduced storage</p>
				<p>C. Increased inconsistency</p>
				<p>D. Faster processing</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>53)</b> Updating the same customer address in multiple tables is an example of:</h4>
				<p>A. Validation</p>
				<p>B. Normalisation</p>
				<p>C. Redundancy problem</p>
				<p>D. Encryption</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>54)</b> Atomic values mean:</h4>
				<p>A. Complex data types</p>
				<p>B. Multiple values in one field</p>
				<p>C. Single indivisible values</p>
				<p>D. Encrypted values</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>55)</b> Which violates First Normal Form (1NF)?</h4>
				<p>A. Unique primary key</p>
				<p>B. Multiple phone numbers in one field</p>
				<p>C. Atomic values</p>
				<p>D. Single data type per column</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>56)</b> Second Normal Form removes:</h4>
				<p>A. Transitive dependencies</p>
				<p>B. Partial dependencies</p>
				<p>C. Primary keys</p>
				<p>D. Foreign keys</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>57)</b> Third Normal Form removes:</h4>
				<p>A. Partial dependency</p>
				<p>B. Lookup tables</p>
				<p>C. Transitive dependency</p>
				<p>D. Atomic values</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>58)</b> A composite primary key:</h4>
				<p>A. Uses multiple fields to identify a record</p>
				<p>B. Uses only one field</p>
				<p>C. Is optional</p>
				<p>D. Stores duplicates</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>59)</b> Which scenario requires a composite key?</h4>
				<p>A. Unique ID system</p>
				<p>B. Order details table linking OrderID and ProductID</p>
				<p>C. Customer name table</p>
				<p>D. Single record database</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>60)</b> A foreign key must match a:</h4>
				<p>A. Unique constraint</p>
				<p>B. Validation rule</p>
				<p>C. Primary key in another table</p>
				<p>D. Data type</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>
<section> 
	<section>
		<h4><b>61)</b> Referential integrity ensures:</h4>
				<p>A. No duplicate emails</p>
				<p>B. Foreign keys match existing primary keys</p>
				<p>C. All data encrypted</p>
				<p>D. Tables are merged</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>62)</b> Which relationship allows multiple records in both tables?</h4>
				<p>A. One-to-one</p>
				<p>B. One-to-many</p>
				<p>C. Many-to-many</p>
				<p>D. No relationship</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>63)</b> A many-to-many relationship requires:</h4>
				<p>A. No keys</p>
				<p>B. An intermediate linking table</p>
				<p>C. Single entity</p>
				<p>D. Removal of constraints</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>64)</b> In an ER diagram, relationships are represented by:</h4>
				<p>A. Lines</p>
				<p>B. Rectangles</p>
				<p>C. Circles</p>
				<p>D. Squares</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>65)</b> A NOT NULL constraint ensures:</h4>
				<p>A. No duplicates</p>
				<p>B. Field must contain data</p>
				<p>C. Data is encrypted</p>
				<p>D. Field length fixed</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>66)</b> A UNIQUE constraint prevents:</h4>
				<p>A. Missing values</p>
				<p>B. Duplicate values</p>
				<p>C. Data validation</p>
				<p>D. Relationships</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>67)</b> Which Big Data characteristic refers to trustworthiness?</h4>
				<p>A. Volume</p>
				<p>B. Velocity</p>
				<p>C. Variety</p>
				<p>D. Veracity</p>
	</section> 
	<section><p><b>Answer: D</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>68)</b> Velocity in Big Data refers to:</h4>
				<p>A. Amount of storage</p>
				<p>B. Speed of data generation</p>
				<p>C. Data format</p>
				<p>D. Data encryption</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>69)</b> Which example represents Variety?</h4>
				<p>A. 1TB dataset</p>
				<p>B. Real-time sensor updates</p>
				<p>C. Text, video, and image data combined</p>
				<p>D. Data reliability</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>70)</b> Data analytics helps organisations to:</h4>
				<p>A. Delete records</p>
				<p>B. Identify patterns</p>
				<p>C. Increase redundancy</p>
				<p>D. Remove keys</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>
<section> 
	<section>
		<h4><b>71)</b> A logical data model focuses on:</h4>
				<p>A. Hardware setup</p>
				<p>B. Physical storage disks</p>
				<p>C. Structure of entities and relationships</p>
				<p>D. Network speed</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>72)</b> An entity must have:</h4>
				<p>A. At least one attribute</p>
				<p>B. At least one primary key</p>
				<p>C. A foreign key</p>
				<p>D. Duplicate values</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>73)</b> Which is an example of a transitive dependency?</h4>
				<p>A. StudentID → StudentName</p>
				<p>B. OrderID → CustomerID → CustomerAddress</p>
				<p>C. ProductID → Price</p>
				<p>D. ISBN → BookTitle</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>74)</b> Normalisation improves:</h4>
				<p>A. Storage waste</p>
				<p>B. Data consistency</p>
				<p>C. Redundancy</p>
				<p>D. Complexity</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>75)</b> A JSON datatype is used for:</h4>
				<p>A. Boolean storage</p>
				<p>B. Structured data storage</p>
				<p>C. Numeric data only</p>
				<p>D. Images only</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>76)</b> Which SQL datatype stores TRUE/FALSE values?</h4>
				<p>A. CHAR</p>
				<p>B. BOOLEAN</p>
				<p>C. INT</p>
				<p>D. FLOAT</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>77)</b> Which is best stored as CHAR instead of VARCHAR?</h4>
				<p>A. Variable-length names</p>
				<p>B. Fixed-length country codes</p>
				<p>C. Long descriptions</p>
				<p>D. JSON files</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>78)</b> An audit trail records:</h4>
				<p>A. Data encryption</p>
				<p>B. Data changes and actions</p>
				<p>C. File size</p>
				<p>D. Data type</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>79)</b> If a user leaves a required field empty, which validation fails?</h4>
				<p>A. Range</p>
				<p>B. Lookup</p>
				<p>C. Presence</p>
				<p>D. Length</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>80)</b> A timestamp is often used to:</h4>
				<p>A. Store passwords</p>
				<p>B. Track record creation/modification time</p>
				<p>C. Store decimal numbers</p>
				<p>D. Validate ranges</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
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

