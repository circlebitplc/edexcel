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

	<meta name='description' content='Topic 12 – Manipulating data'>
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
					<h4>Topic 12</h4>
					<h2> Manipulating data</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

<section>
  <h3>SECTION 12.1 – DATA INTEGRITY AND DATA GOVERNANCE</h3>
</section>

<section>
  <section>
    <h4><b>1) </b>What is meant by the term data integrity?</h4>
  </section>
  <section>
    <p>Data integrity refers to the accuracy, consistency and reliability of data, ensuring that data remains correct, complete and unaltered throughout its entire lifecycle. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>2) </b>Explain why data integrity is important when data is stored in an organisation?</h4>
  </section>
  <section>
    <p>Data integrity is important because organisations rely on accurate and consistent data to make effective decisions. If stored data lacks integrity, decisions may be based on incorrect information, leading to financial loss, operational inefficiency and damage to the organisation’s reputation. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>3) </b>Describe four factors that affect data integrity?</h4>
  </section>
  <section>
    <p>One factor is accuracy, which ensures data is entered correctly. Another factor is consistency, which ensures the same data values are used across different systems. A third factor is completeness, which ensures all required data is present. A fourth factor is security, which prevents unauthorised access or modification of data. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>4) </b>Explain how inaccurate data can affect organisational decision making?</h4>
  </section>
  <section>
    <p>If data is inaccurate, managers may make decisions based on false information. This can result in poor planning, wasted resources and incorrect strategic decisions. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>5) </b>Explain how incomplete data can reduce the reliability of a database?</h4>
  </section>
  <section>
    <p>Incomplete data means important information is missing from records. As a result, reports generated from the database may be misleading, reducing trust in the system. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>6) </b>Explain why consistency of data is important across an organisation?</h4>
  </section>
  <section>
    <p>Consistency ensures the same data values are used in all departments and systems. This prevents conflicting information and ensures accurate reporting and analysis. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>7) </b>Describe the role of reliability in maintaining data integrity?</h4>
  </section>
  <section>
    <p>Reliable data can be trusted to be correct and up to date. This allows users to confidently use the data for decision making without repeatedly checking its accuracy. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>8) </b>Explain how security contributes to maintaining data integrity?</h4>
  </section>
  <section>
    <p>Security controls restrict access to authorised users only. This prevents unauthorised changes and protects data from accidental or malicious corruption. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>9) </b>Describe four methods that organisations can use to maintain data integrity?</h4>
  </section>
  <section>
    <p>Organisations can use validation rules to prevent incorrect data entry, verification to confirm correct entry, access controls to restrict who can modify data, and regular backups to restore data if corruption occurs. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>10) </b>Explain how validation helps to improve data integrity?</h4>
  </section>
  <section>
    <p>Validation checks data against predefined rules before it is stored. This reduces errors at the point of data entry and prevents invalid data from being saved. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>11) </b>Explain how verification helps to improve data integrity?</h4>
  </section>
  <section>
    <p>Verification ensures data has been entered correctly by comparing it with the original source, helping to detect and correct errors. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>12) </b>Describe the purpose of audit trails in maintaining data integrity?</h4>
  </section>
  <section>
    <p>Audit trails record who accessed data, what changes were made and when those changes occurred. This allows errors to be traced and corrected, unauthorised actions to be detected, investigations to be carried out, and accountability to be maintained, helping to preserve data integrity. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>13) </b>State four items of information that are commonly stored in an audit trail?</h4>
  </section>
  <section>
    <p>Audit trails store the user ID, date and time of access, details of changes made, and the type of action performed. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>14) </b>Explain why audit trails are important for accountability?</h4>
  </section>
  <section>
    <p>Audit trails allow actions to be traced back to individual users. This ensures responsibility for actions and discourages misuse of systems. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>15) </b>Explain how regular backups help to maintain data integrity?</h4>
  </section>
  <section>
    <p>Regular backups allow accurate data to be restored if data is lost, corrupted or altered, ensuring data integrity can be maintained after failures. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>16) </b>Explain how version control can improve data integrity?</h4>
  </section>
  <section>
    <p>Version control records changes made to data over time. If errors occur, a previous correct version can be restored, maintaining data accuracy. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>17) </b>Describe two consequences of poor data integrity in an organisation?</h4>
  </section>
  <section>
    <p>One consequence of poor data integrity is incorrect business decisions, where managers rely on inaccurate or inconsistent data, leading to financial loss and poor strategic planning. Another consequence is loss of customer trust or legal penalties, as unreliable or incorrect data can result in regulatory breaches and damage to the organisation’s reputation. [4]</p>
  </section>
</section>
<section>
  <h3>SECTION 12.2 – DATA ACCESS CONTROL AND AUTHENTICATION</h3>
</section>

<section>
  <section>
    <h4><b>18) </b>What is meant by authentication?</h4>
  </section>
  <section>
    <p>Authentication is the process of confirming the identity of a user before allowing access to a system. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>19) </b>Describe two methods of user authentication?</h4>
  </section>
  <section>
    <p>Two authentication methods are username and password systems, and biometric methods such as fingerprint or facial recognition. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>20) </b>Explain the use of usernames and passwords as an authentication method?</h4>
  </section>
  <section>
    <p>The username identifies the user, while the password proves their identity. Access is granted only when the correct password is entered. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>21) </b>Explain the purpose of multi factor authentication?</h4>
  </section>
  <section>
    <p>Multi factor authentication improves security by requiring more than one authentication method, reducing the risk of unauthorised access. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>22) </b>Explain how digital certificates are used in authentication?</h4>
  </section>
  <section>
    <p>Digital certificates use encryption and trusted certificate authorities to verify the identity of users or devices during secure communication. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>23) </b>What is meant by authorisation?</h4>
  </section>
  <section>
    <p>Authorisation determines what data or actions a user is allowed to access after authentication. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>24) </b>Explain the difference between authentication and authorisation?</h4>
  </section>
  <section>
    <p>Authentication verifies who the user is, while authorisation determines what actions the user is permitted to perform. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>25) </b>Describe role based access control?</h4>
  </section>
  <section>
    <p>Role based access control assigns permissions based on a user’s job role, ensuring access is limited to what is necessary. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>26) </b>Describe attribute based access control?</h4>
  </section>
  <section>
    <p>Attribute based access control uses attributes such as department, location or time to determine access permissions. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>27) </b>Describe permission based access control?</h4>
  </section>
  <section>
    <p>Permission based access control assigns specific access rights directly to individual users. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>28) </b>Explain how access control methods help to protect data integrity?</h4>
  </section>
  <section>
    <p>Access control methods prevent unauthorised users from viewing or modifying data, protecting it from tampering. This reduces accidental errors by limiting access to trained users only. Access control also prevents malicious changes by restricting permissions, and ensures that only authorised users can update data correctly, maintaining accuracy and consistency across the system. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>29) </b>Explain why access levels are important in multi user systems?</h4>
  </section>
  <section>
    <p>Access levels ensure users only access appropriate data, reducing the risk of accidental deletion or modification of important information. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>30) </b>Describe two risks of weak authentication systems?</h4>
  </section>
  <section>
    <p>One risk of weak authentication systems is unauthorised access, where attackers can gain entry to systems, leading to data breaches and loss of sensitive information. Another risk is data being altered, stolen or misused, as weak authentication makes it easier for malicious users to manipulate data, causing financial loss, legal issues or damage to the organisation’s reputation. [4]</p>
  </section>
</section>

<section>
  <h3>SECTION 12.3 – DATA DICTIONARIES</h3>
</section>

<section>
  <section>
    <h4><b>31) </b>What is meant by the term data dictionary?</h4>
  </section>
  <section>
    <p>A data dictionary is a document that defines and describes all data items in a database, including names, data types, lengths and validation rules. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>32) </b>Explain why a data dictionary is created before building a database?</h4>
  </section>
  <section>
    <p>A data dictionary is created before building a database to ensure all data items are clearly defined and agreed. This reduces development errors and ensures consistency and data integrity. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>33) </b>Describe four items of information stored in a data dictionary?</h4>
  </section>
  <section>
    <p>A data dictionary stores field names, data types, field lengths and validation rules to control data entry. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>34) </b>Explain the purpose of field names in a data dictionary?</h4>
  </section>
  <section>
    <p>Field names uniquely identify data items and ensure consistent naming across the database. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>35) </b>Explain the purpose of descriptions in a data dictionary?</h4>
  </section>
  <section>
    <p>Descriptions explain what each field represents, helping users and developers interpret data correctly. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>36) </b>Explain why data types are specified in a data dictionary?</h4>
  </section>
  <section>
    <p>Data types ensure only appropriate values are stored and prevent invalid data entry. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>37) </b>Explain how field length helps maintain data integrity?</h4>
  </section>
  <section>
    <p>Field length limits prevent data from exceeding expected sizes or being truncated. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>38) </b>Explain the purpose of validation rules in a data dictionary?</h4>
  </section>
  <section>
    <p>Validation rules restrict input to acceptable values, reducing data entry errors. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>39) </b>Describe how a data dictionary helps maintain consistency across databases?</h4>
  </section>
  <section>
    <p>A data dictionary helps maintain consistency by providing standard field names so the same data items are used across all databases. It defines data types to ensure data is stored in the same format. Field lengths are specified to prevent variation in data size, and validation rules ensure data follows the same rules in every system, maintaining consistent meaning and structure. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>40) </b>Explain how a data dictionary supports database maintenance?</h4>
  </section>
  <section>
    <p>It allows developers to understand database structure quickly, making maintenance and updates easier. [2]</p>
  </section>
</section>

<section>
  <h3>SECTION 12.4 – DATA TYPES AND STRUCTURED QUERY LANGUAGE (SQL)</h3>
</section>

<section>
  <section>
    <h4><b>41) </b>What is meant by structured data?</h4>
  </section>
  <section>
    <p>Structured data is data organised into predefined formats such as tables with rows and columns. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>42) </b>Explain why SQL databases require defined data types?</h4>
  </section>
  <section>
    <p>Defined data types ensure data is stored correctly and efficiently, improving performance and preventing invalid data entry. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>43) </b>Describe four integer data types used in SQL databases?</h4>
  </section>
  <section>
    <p>TINYINT is used to store very small whole numbers. SMALLINT stores small whole numbers with a larger range than TINYINT. INT is used to store standard whole numbers in most databases. BIGINT is used to store very large whole numbers beyond the range of INT. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>44) </b>Explain the difference between INT and BIGINT?</h4>
  </section>
  <section>
    <p>INT stores standard sized integers, while BIGINT stores much larger numerical values. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>45) </b>Explain the purpose of DECIMAL data types?</h4>
  </section>
  <section>
    <p>DECIMAL data types store exact numeric values, making them suitable for financial calculations. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>46) </b>Explain why FLOAT and REAL data types are used?</h4>
  </section>
  <section>
    <p>FLOAT and REAL store approximate decimal values where exact precision is not required. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>47) </b>Explain the purpose of DATE data types?</h4>
  </section>
  <section>
    <p>DATE data types store calendar dates without time information. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>48) </b>Explain the purpose of TIMESTAMP data types?</h4>
  </section>
  <section>
    <p>TIMESTAMP stores both date and time, often used for logging events. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>49) </b>Explain the difference between CHAR and VARCHAR data types?</h4>
  </section>
  <section>
    <p>CHAR stores fixed length strings, while VARCHAR stores variable length strings, saving storage space. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>50) </b>Explain why NVARCHAR is used for multilingual data?</h4>
  </section>
  <section>
    <p>NVARCHAR supports Unicode characters, allowing text from multiple languages to be stored correctly. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>51) </b>Explain why BOOLEAN data types are used?</h4>
  </section>
  <section>
    <p>BOOLEAN data types store true or false values used in logical decisions. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>52) </b>Explain one limitation of using JSON as a database data type?</h4>
  </section>
  <section>
    <p>JSON data is harder to validate and query efficiently compared to structured relational data. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>53) </b>Explain how inappropriate data types can affect database performance?</h4>
  </section>
  <section>
    <p>Incorrect data types waste storage space and slow data processing. [2]</p>
  </section>
</section>

<section>
  <h3>SECTION 12.5 – DATA VALIDATION</h3>
</section>

<section>
  <section>
    <h4><b>54) </b>What is meant by data validation?</h4>
  </section>
  <section>
    <p>Data validation is the process of checking that input data follows predefined rules before storage. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>55) </b>Explain why data validation is carried out at the point of data entry?</h4>
  </section>
  <section>
    <p>Validating data at entry prevents incorrect data being stored, reducing errors early in the system. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>56) </b>Describe the three main stages of data validation?</h4>
  </section>
  <section>
    <p>The first stage is data input, where data is entered into the system. The second stage is validation checking, where the data is checked against predefined rules. The final stage is error handling, where invalid data is rejected and the user is prompted to correct the input. [3]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>57) </b>Explain the purpose of presence check validation?</h4>
  </section>
  <section>
    <p>Presence checks ensure required fields are not left blank. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>58) </b>Explain the purpose of range check validation?</h4>
  </section>
  <section>
    <p>Range checks ensure values fall within acceptable limits. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>59) </b>Explain the purpose of lookup check validation?</h4>
  </section>
  <section>
    <p>Lookup checks ensure data matches predefined values. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>60) </b>Explain the purpose of list check validation?</h4>
  </section>
  <section>
    <p>List checks restrict entries to allowed values. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>61) </b>Explain the purpose of length check validation?</h4>
  </section>
  <section>
    <p>Length checks limit the number of characters entered. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>62) </b>Explain the purpose of format check validation?</h4>
  </section>
  <section>
    <p>Format checks ensure data follows a required pattern. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>63) </b>Explain the purpose of check digit validation?</h4>
  </section>
  <section>
    <p>Check digits detect data entry errors using mathematical calculations. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>64) </b>Describe four real life situations where data validation rules are used?</h4>
  </section>
  <section>
    <p>Data validation rules are used when entering email addresses to ensure the correct format is used. Phone numbers are validated to check length and numeric format. Dates of birth are validated to ensure realistic date ranges are entered. Bank account numbers are validated using length checks or check digits to detect errors. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>65) </b>Explain why validation does not guarantee data accuracy?</h4>
  </section>
  <section>
    <p>Validation checks format and rules, not whether the data is correct in reality. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>66) </b>Explain the difference between validation and verification?</h4>
  </section>
  <section>
    <p>Validation checks data against rules, while verification checks data against the original source. [2]</p>
  </section>
</section>

<section>
  <h3>SECTION 12.6 – RELATIONAL DATABASES AND DATA REDUNDANCY</h3>
</section>

<section>
  <section>
    <h4><b>67) </b>What is meant by a relational database?</h4>
  </section>
  <section>
    <p>A relational database stores data in tables that are linked using relationships. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>68) </b>Explain how relationships are created between tables?</h4>
  </section>
  <section>
    <p>Relationships are created using primary keys and foreign keys. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>69) </b>What is meant by data redundancy?</h4>
  </section>
  <section>
    <p>Data redundancy is the unnecessary duplication of data. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>70) </b>Explain why data redundancy should be avoided?</h4>
  </section>
  <section>
    <p>Data redundancy wastes storage space and increases the risk of data inconsistency. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>71) </b>Explain how data redundancy increases storage costs?</h4>
  </section>
  <section>
    <p>Duplicate data requires additional storage capacity. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>72) </b>Explain how data redundancy can lead to data inconsistency?</h4>
  </section>
  <section>
    <p>If one copy is updated and others are not, the data becomes inconsistent. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>73) </b>Explain how data redundancy can affect data maintenance?</h4>
  </section>
  <section>
    <p>More duplicate data means more updates are required, increasing the chance of errors. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>74) </b>Describe one situation where limited redundancy may be acceptable?</h4>
  </section>
  <section>
    <p>Limited redundancy may be used in backup systems to protect against data loss. [2]</p>
  </section>
</section>

<section>
  <h3>SECTION 12.7 – DATA NORMALISATION</h3>
</section>

<section>
  <section>
    <h4><b>75) </b>What is meant by data normalisation?</h4>
  </section>
  <section>
    <p>Normalisation is the process of organising data to reduce redundancy and improve data integrity. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>76) </b>Explain why data normalisation is carried out?</h4>
  </section>
  <section>
    <p>Normalisation reduces duplication and ensures consistent and accurate data across tables. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>77) </b>Explain the purpose of first normal form (1NF)?</h4>
  </section>
  <section>
    <p>First normal form ensures data is atomic and removes repeating groups. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>78) </b>State three rules that must be met for a table to be in 1NF?</h4>
  </section>
  <section>
    <p>Each field must contain atomic values, there must be no repeating groups, and a primary key must exist. [3]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>79) </b>Explain the purpose of a primary key?</h4>
  </section>
  <section>
    <p>A primary key uniquely identifies each record in a table. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>80) </b>Explain the purpose of second normal form (2NF)?</h4>
  </section>
  <section>
    <p>Second normal form removes partial dependencies. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>81) </b>Explain what is meant by partial dependency?</h4>
  </section>
  <section>
    <p>A partial dependency occurs when a non key attribute depends on part of a composite key. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>82) </b>Explain the purpose of third normal form (3NF)?</h4>
  </section>
  <section>
    <p>Third normal form removes transitive dependencies. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>83) </b>Explain what is meant by transitive dependency?</h4>
  </section>
  <section>
    <p>A transitive dependency occurs when a non key attribute depends on another non key attribute. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>84) </b>Describe the process of converting an unnormalised table into 3NF?</h4>
  </section>
  <section>
    <p>First, the unnormalised table is converted into first normal form (1NF) by removing repeating groups so that each field contains atomic values. Next, the table is converted into second normal form (2NF) by removing partial dependencies, where non-key attributes depend on only part of a composite key. Finally, the table is converted into third normal form (3NF) by removing transitive dependencies, ensuring that non-key attributes depend only on the primary key. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>85) </b>Explain two benefits and two drawbacks of data normalisation?</h4>
  </section>
  <section>
    <p>One benefit of data normalisation is reduced data redundancy, as data is stored only once, which saves storage space. Another benefit is improved data integrity, because updates only need to be made in one place, reducing inconsistencies. One drawback of data normalisation is increased complexity, as data is split across multiple tables, making the database harder to design and maintain. Another drawback is slower query performance, because data often needs to be retrieved using multiple table joins. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>86) </b>Explain how normalisation helps reduce data anomalies?</h4>
  </section>
  <section>
    <p>Normalisation prevents update, insert and delete anomalies. [2]</p>
  </section>
</section>

<section>
  <h3>SECTION 12.8 – ENTITY RELATIONSHIP DIAGRAMS (ERDs)</h3>
</section>

<section>
  <section>
    <h4><b>87) </b>What is meant by a logical data model?</h4>
  </section>
  <section>
    <p>A logical data model shows how data is structured logically without physical implementation details. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>88) </b>Explain the purpose of an entity in a database?</h4>
  </section>
  <section>
    <p>An entity represents a real world object whose data is stored in the database. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>89) </b>Explain the purpose of attributes in an entity?</h4>
  </section>
  <section>
    <p>Attributes store the properties or characteristics of an entity. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>90) </b>Explain the purpose of relationships in an ERD?</h4>
  </section>
  <section>
    <p>Relationships show how entities are connected to each other. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>91) </b>Describe one to one relationships?</h4>
  </section>
  <section>
    <p>Each record in one entity relates to only one record in another entity. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>92) </b>Describe one to many relationships?</h4>
  </section>
  <section>
    <p>One record in an entity relates to many records in another entity. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>93) </b>Describe many to many relationships?</h4>
  </section>
  <section>
    <p>Many records in one entity relate to many records in another entity. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>94) </b>Explain the purpose of foreign keys?</h4>
  </section>
  <section>
    <p>Foreign keys link tables together and enforce referential integrity. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>95) </b>Explain how constraints help maintain data integrity?</h4>
  </section>
  <section>
    <p>Constraints restrict invalid data entry and enforce data rules. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>96) </b>Explain how ERDs are used during database design?</h4>
  </section>
  <section>
    <p>ERDs are used during database design to plan the overall structure of the database by identifying entities and their attributes. They define the relationships between entities, including one-to-one, one-to-many and many-to-many relationships. ERDs also help identify primary keys and foreign keys needed to link tables. In addition, they allow constraints to be identified before implementation, reducing design errors and improving data integrity. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>97) </b>Explain why ERDs are created before database implementation?</h4>
  </section>
  <section>
    <p>ERDs identify design issues early, reducing errors during implementation. [2]</p>
  </section>
</section>

<section>
  <h3>SECTION 12.9 – BIG DATA</h3>
</section>

<section>
  <section>
    <h4><b>98) </b>What is meant by Big Data?</h4>
  </section>
  <section>
    <p>Big Data refers to extremely large and complex datasets that cannot be processed efficiently using traditional data processing methods. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>99) </b>Describe the five Vs of Big Data?</h4>
  </section>
  <section>
    <p>The five Vs of Big Data are volume, velocity, variety, veracity and value. Volume refers to the extremely large amounts of data generated and stored. Velocity refers to the speed at which data is produced and processed, often in real time. Variety refers to the different types and formats of data, including structured and unstructured data. Veracity refers to the accuracy and reliability of data, which may vary between sources. Value refers to how useful the data is in providing insights that support decision making and improve organisational performance. [5]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>100) </b>Explain what is meant by volume in Big Data?</h4>
  </section>
  <section>
    <p>Volume refers to the extremely large quantities of data generated and stored in Big Data systems. This data is produced continuously from many sources such as sensors, transactions and social media. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>101) </b>Explain what is meant by velocity in Big Data?</h4>
  </section>
  <section>
    <p>Velocity refers to the speed at which data is generated, collected and processed. In many cases, this data must be analysed in real time or near real time. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>102) </b>Explain what is meant by variety in Big Data?</h4>
  </section>
  <section>
    <p>Variety refers to the wide range of data types and formats used in Big Data, including structured, semi structured and unstructured data. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>103) </b>Explain what is meant by veracity in Big Data?</h4>
  </section>
  <section>
    <p>Veracity refers to the accuracy, reliability and quality of data. Data from multiple sources may be incomplete or incorrect, affecting analysis results. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>104) </b>Explain what is meant by value in Big Data?</h4>
  </section>
  <section>
    <p>Value refers to the usefulness of Big Data, focusing on how analysis produces insights that support decision making and improve organisational performance. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>105) </b>Explain how Big Data can be used by organisations to improve decision making?</h4>
  </section>
  <section>
    <p>Big Data can be analysed to identify patterns and trends that are not visible in smaller datasets. This allows organisations to make accurate forecasts and predictions about future outcomes. Big Data can also be used to support real-time decision making, enabling organisations to respond quickly to customer behaviour and market changes, leading to more informed and effective decisions. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>106) </b>Describe two benefits and two challenges of using Big Data?</h4>
  </section>
  <section>
    <p>One benefit of using Big Data is improved decision making, as analysing large datasets provides better insights and more accurate predictions. Another benefit is increased efficiency, because organisations can optimise processes by identifying patterns and inefficiencies. One challenge of using Big Data is the high cost of storage and processing infrastructure. Another challenge is managing data privacy and security, as large volumes of sensitive data increase the risk of data breaches and misuse. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>107) </b>Explain two ethical issues related to the use of Big Data?</h4>
  </section>
  <section>
    <p>One ethical issue is invasion of privacy, where personal data may be collected, stored or analysed without the individual’s informed consent, leading to a loss of control over personal information. Another ethical issue is misuse of data, where organisations may analyse or share data in ways that are unfair or discriminatory, potentially harming individuals or groups. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>108) </b>Explain why data privacy is a major concern in Big Data systems?</h4>
  </section>
  <section>
    <p>Big Data systems store large volumes of sensitive personal data, increasing the risk of unauthorised access, data breaches and misuse. [2]</p>
  </section>
</section>
			 
	 
 

			</div>
		</div>

		<script src="<?= $pptBase ?>/lib/js/head.min.js"></script>
		<script src="<?= $pptBase ?>/js/reveal.min.js"></script>

		<script>
 
			Reveal.initialize({
				controls: true,
				progress: true,
				history: true,
				center: true,

				theme: Reveal.getQueryHash().theme,  
				transition: Reveal.getQueryHash().transition || 'default',  
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

