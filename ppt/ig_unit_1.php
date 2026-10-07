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

	<meta name='description' content='Unit 1 – Digital devices'>
	<meta name='author' content='Enidu Batuwanthudawe'>

	<meta name='apple-mobile-web-app-capable' content='yes' />
	<meta name='apple-mobile-web-app-status-bar-style' content='black-translucent' />

	<meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no'>
	
	<link rel='stylesheet' href='<?= $pptBase ?>/css/reveal.min.css'>
	<link rel='stylesheet' href='<?= $pptBase ?>/css/theme/default.css' id='theme'>
	<link rel='stylesheet' href='<?= $pptBase ?>/css/custom.css'>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
					<h4>Unit 1</h4>
					<h2> Digital devices</h2>
					<?= ppt_teacher_credit_markup() ?>
<?php 
include ('/auth.php');
?>

 </section>
<section>
  <section><h4><b>1) </b>Define the term digital device?</h4></section>
  <section><p>A digital device is an electronic device that accepts input data, processes it, stores it, and produces output using digital signals.</p></section>
  <section><p>These devices represent all data using binary digits (0s and 1s), which allows the data to be processed accurately by a computer system.</p></section>
  <section><p>Digital devices use microprocessors to carry out instructions, enabling them to perform tasks such as calculations, communication, data storage, and control.
Because data is handled in discrete values, digital devices are more reliable, accurate, and less affected by noise or interference than analogue devices.[2]</p></section>
</section>

<section>
  <section><h4><b>2) </b>State two examples of digital devices?</h4></section>
  <section><p>Smartphone</p></section>
  <section><p>Laptop</p></section>
  <section><p>Smartphone</p></section>
  <section><p>Desktop computer</p></section>
  <section><p>Game console</p></section>
  <section><p>Smart TVr</p></section>
  <section><p>Digital camera [2]</p></section>
</section>

<section>
  <section><h4><b>3) </b>Describe two ways digital devices are used in everyday life?</h4></section>
  <section><p>Communication<br>
Digital devices such as smartphones and computers are used to communicate through emails, instant messaging, video calls, and social media. This allows people to send and receive information quickly over long distances, making communication faster and more convenient.</p></section>
  <section><p>Information access and work/study<br>
Digital devices are used to access information on the internet, complete schoolwork or office tasks, and store documents. Users can create documents, research topics online, and save files electronically, improving efficiency and organisation. [4]</p></section>
</section>

<section>
  <section><h4><b>4) </b>Define the term computer?</h4></section>
  <section><p>A computer is an electronic device that accepts data as digital input, processes the data according to instructions (a program), stores data, and produces information as digital output. [1]</p></section>
</section>

<section>
  <section><h4><b>5) </b>Explain the difference between a computer and a digital device?</h4></section>
  <section><p>A computer is a type of digital device that is designed to process data using programs, allowing users to perform a wide range of tasks such as creating documents, running software, storing large amounts of data, and communicating over networks.</p></section>
  <section><p>A digital device is a broader term that refers to any electronic device that uses digital (binary) signals to process or transmit data. Digital devices may have limited functions and do not always run complex programs like a computer.[3]</p></section>
</section>

<section>
  <section><h4><b>6) </b>Define the term microprocessor?</h4></section>
  <section><p>A microprocessor is a single silicon chip that performs the functions of the CPU, including calculations, decision-making, and control. It processes digital data using binary and manages the input, processing, storage, and output activities of a computer or digital device.[2]</p></section>
</section>

<section>
  <section><h4><b>7) </b>State two uses of mainframe computers?</h4></section>
  <section><p>Banking and financial transaction processing<br>

Airline and railway reservation systems<br>

Government databases (e.g. census and national records)<br>

Large organisation payroll processing<br>

Insurance policy and claim processing

Large retail stock control systems</p></section> >
</section>

<section>
  <section><h4><b>8) </b>Explain why organizations use mainframe computers?</h4></section>
  <section><p>Organisations use mainframe computers because they can process very large amounts of data quickly and reliably. Mainframes are designed to support hundreds or thousands of users at the same time, making them suitable for large organisations such as banks, airlines, and government departments.</p></section>
  <section><p>Mainframe computers are also highly reliable and secure. They can run continuously for long periods without failure and include advanced security features to protect sensitive data, such as financial or personal records. This reduces the risk of data loss and system downtime.</p></section>
  <section><p>Mainframes are highly reliable with minimal downtime.</p></section>
  <section><p>In addition, mainframes can handle high volumes of transactions simultaneously, ensuring that critical operations like payroll processing, reservations, and database management are completed efficiently and accurately.[4]</p></section>
</section>

<section>
  <section><h4><b>9) </b>State two uses of supercomputers?</h4></section>
  <section><p>Weather forecasting<br>
Supercomputers are used to analyse vast amounts of atmospheric data to predict weather patterns and natural disasters.</p></section>
  <section><p>Scientific research and simulations<br>
They are used for complex calculations such as climate modelling, space research, and nuclear or medical simulations.[2]</p></section>
</section>

<section>
  <section><h4><b>10) </b>Explain one use of a supercomputer?</h4></section>
  <section><p>Supercomputers are used for weather forecasting. They can process extremely large amounts of data collected from satellites, sensors, and weather stations. By performing billions of calculations per second, supercomputers create accurate weather models that help predict storms, rainfall, and climate changes, allowing governments and organisations to prepare for natural disasters.</p></section>
  <section><p>Medical and scientific research<br>
Supercomputers are used to analyse complex data in medical research, such as DNA sequencing and drug development. They can process massive datasets very quickly, helping scientists model how diseases spread and test new treatments more efficiently.[3]</p></section>
  <section><p>Space research and astronomy<br>
Supercomputers are used to process data from telescopes and space missions. They help scientists simulate the movement of planets, study galaxies, and plan space missions by performing extremely complex calculations in a short time.[3]</p></section>
</section>

<section>
  <section><h4><b>11) </b>Describe the difference between a mainframe computer and a supercomputer?</h4></section>
  <section><p>A mainframe computer is designed to process large volumes of data and transactions for many users at the same time. It is commonly used by large organisations such as banks, airlines, and government departments for tasks like payroll processing, database management, and reservation systems.</p></section>
  <section><p>A supercomputer, on the other hand, is designed to perform extremely complex calculations at very high speeds. It is mainly used for scientific research, such as weather forecasting, climate modelling, medical research, and space exploration.[4]</p></section>
</section>

<section>
  <section><h4><b>12) </b>Define the term personal computer (PC)?</h4></section>
  <section><p>A personal computer is a standalone computer that uses a microprocessor to process data and run programs. It is intended for individual use in homes, schools, and offices, and allows users to perform a wide range of everyday tasks efficiently.[1]</p></section>
</section>

<section>
  <section><h4><b>13) </b>State two features of a desktop computer?</h4></section>
  <section><p>Separate components<br>
A desktop computer usually has separate parts such as a monitor, system unit (CPU case), keyboard, and mouse.</p></section>
  <section><p>Not portable<br>
Desktop computers are designed to be used in one location and are not easily moved because they require mains power and external peripherals. [2]</p></section>

<section><p>Easily upgradeable<br>
Components such as RAM, storage drives, and graphics cards can usually be upgraded or replaced easily.</p></section>
<section><p>Uses mains electricity<br>
A desktop computer is powered directly from a mains electricity supply and does not have a built-in battery.</p></section>
</section>
<section>
	
	
  <section><h4><b>14) </b>Explain one advantage and one disadvantage of desktop computers?</h4></section>
  <section><p>Advantage:<br>
Desktop computers are easily upgradeable. Components such as RAM, storage, and graphics cards can be replaced or improved, allowing the computer to remain useful for longer and handle more demanding tasks.</p></section>
  <section><p>Disadvantage:<br>
Desktop computers are not portable. They are large and require mains electricity, so they must be used in a fixed location and cannot be easily moved or used while travelling.[4]</p></section>
</section>

<section>
  <section><h4><b>15) </b>Define the term all-in-one computer?</h4></section>
  <section><p>An all-in-one computer is a type of desktop computer in which all the main components, such as the processor, memory, storage, and speakers, are built into the monitor, rather than being in a separate system unit. [2]</p></section>
</section>

<section>
  <section><h4><b>16) </b>Explain why all-in-one computers are space-saving?</h4></section>
  <section><p>All-in-one computers are space-saving because the main internal components such as the processor, memory, and storage are built into the monitor instead of being housed in a separate system unit. This removes the need for a large computer case under or beside the desk.</p></section>
  <section><p>As a result, fewer cables are required and less desk and floor space is used, making all-in-one computers ideal for small offices, classrooms, and home environments.[3]</p></section>
</section>

<section>
  <section><h4><b>17) </b>Define the term laptop computer?</h4></section>
  <section><p>A laptop computer is a portable personal computer that has an integrated screen, keyboard, pointing device, battery, and internal components combined into a single compact unit, allowing it to be used while being moved.[1]</p></section>
</section>

<section>
  <section><h4><b>18) </b>State two advantages of laptops compared to desktops?</h4></section>
  <section><p>Portable<br>Laptops are lightweight and compact, allowing users to carry them easily and use them in different locations.</p></section>
  <section><p>Built-in battery<br>Laptops can operate without a mains electricity supply for several hours, making them useful during travel or power cuts.</p></section>
  <section><p>Take up less space<br>Laptops have a compact, all-in-one design and do not require separate components like a monitor or system unit, so they use less desk space.</p></section>
  <section><p>Built-in devices<br>Laptops include built-in components such as a webcam, microphone, speakers, and touchpad, reducing the need for extra peripheral devices.</p></section>
</section>

<section>
  <section><h4><b>19) </b>Explain one disadvantage of laptops?</h4></section>
  <section><p>A disadvantage of laptops is that they are difficult and expensive to upgrade or repair. Many components, such as the processor and graphics card, are built into the motherboard, so they cannot be easily replaced. This means the laptop may become outdated more quickly compared to a desktop computer[3]</p></section> 
</section>

<section>
  <section><h4><b>20) </b>Define the term single-board computer (SBC)?</h4></section>
  <section><p>A single-board computer (SBC) is a complete computer built onto a single circuit board, containing the processor, memory, input/output interfaces, and other essential components, designed to perform computing tasks without the need for additional internal boards. [2]</p></section>
</section>

<section>
  <section><h4><b>21) </b>State two uses of single-board computers?</h4></section>
  <section><p>Education and programming</p></section>
  <section><p>Embedded control systems [2]</p></section>
</section>

<section>
  <section><h4><b>22) </b>Explain why SBCs are popular in education?</h4></section>
  <section><p>They are affordable.</p></section>
  <section><p>They support programming and hardware learning.</p></section>
  <section><p>They are small and safe.</p></section>
  <section><p>They encourage hands-on learning. [4]</p></section>
</section>

<section>
  <section><h4><b>23) </b>Define the term SIM card?</h4></section>
  <section><p>A SIM card is a small chip used to identify a user on a mobile network. [1]</p></section>
</section>

<section>
  <section><h4><b>24) </b>Explain the purpose of a SIM card?</h4></section>
  <section><p>It identifies the user on a mobile network.</p></section>
  <section><p>It allows calls, messages and mobile data access.</p></section>
  <section><p>It stores subscriber authentication details. [3]</p></section>
</section>

<section>
  <section><h4><b>25) </b>State two specialist features of mobile phones?</h4></section>
  <section><p>Emergency contact button</p></section>
  <section><p>Basic camera [2]</p></section>
</section>

<section>
  <section><h4><b>26) </b>Explain one specialist feature of a mobile phone?</h4></section>
  <section><p>An emergency button allows instant contact with emergency services.</p></section>
  <section><p>This improves personal safety. [3]</p></section>
</section>

<section>
  <section><h4><b>27) </b>Define the term smartphone?</h4></section>
  <section><p>A smartphone is a mobile phone capable of running applications and accessing the internet. [2]</p></section>
</section>

<section>
  <section><h4><b>28) </b>State three features of smartphones?</h4></section>
  <section><p>Touchscreen</p></section>
  <section><p>Internet connectivity</p></section>
  <section><p>App support [3]</p></section>
</section>

<section>
  <section><h4><b>29) </b>Explain why smartphones require frequent charging?</h4></section>
  <section><p>They use high-resolution screens.</p></section>
  <section><p>They run multiple background apps.</p></section>
  <section><p>They constantly use wireless connections.</p></section>
  <section><p>This consumes significant battery power. [4]</p></section>
</section>

<section>
  <section><h4><b>30) </b>Define the term tablet device?</h4></section>
  <section><p>A tablet is a touchscreen device larger than a smartphone but smaller than a laptop. [2]</p></section>
</section>

<section>
  <section><h4><b>31) </b>State two similarities between tablets and smartphones?</h4></section>
  <section><p>Touchscreen interface</p></section>
  <section><p>Internet connectivity [2]</p></section>
</section>

<section>
  <section><h4><b>32) </b>State two differences between tablets and smartphones?</h4></section>
  <section><p>Tablets have larger screens.</p></section>
  <section><p>Smartphones can make phone calls. [2]</p></section>
</section>

<section>
  <section><h4><b>33) </b>Define the term digital camera?</h4></section>
  <section><p>A digital camera captures images electronically and stores them as digital files. [2]</p></section>
</section>

<section>
  <section><h4><b>34) </b>Explain how frame rate affects video quality?</h4></section>
  <section><p>Higher frame rates produce smoother motion.</p></section>
  <section><p>Lower frame rates cause jerky movement.</p></section>
  <section><p>High frame rates are important for fast scenes.</p></section>
  <section><p>This improves viewing quality. [4]</p></section>
</section>

<section>
  <section><h4><b>35) </b>Define the term resolution?</h4></section>
  <section><p>Resolution is the number of pixels used to display an image. [2]</p></section>
</section>

<section>
  <section><h4><b>36) </b>Explain how resolution affects image quality?</h4></section>
  <section><p>Higher resolution provides sharper images.</p></section>
  <section><p>Lower resolution causes pixelation.</p></section>
  <section><p>Low-resolution images lose quality when enlarged.</p></section>
  <section><p>Higher resolution improves image clarity. [4]</p></section>
</section>

<section>
  <section><h4><b>37) </b>Define the term home entertainment system?</h4></section>
  <section><p>A home entertainment system is a set of devices used to play audio and video at home. [2]</p></section>
</section>

<section>
  <section><h4><b>38) </b>State two features of modern televisions?</h4></section>
  <section><p>High-definition display</p></section>
  <section><p>Internet connectivity [2]</p></section>
</section>

<section>
  <section><h4><b>39) </b>Explain the difference between HD and 4K televisions?</h4></section>
  <section><p>4K TVs have much higher resolution.</p></section>
  <section><p>They display more pixels.</p></section>
  <section><p>This provides sharper images.</p></section>
  <section><p>4K offers better viewing quality. [4]</p></section>
</section>

<section>
  <section><h4><b>40) </b>Define the term sound system?</h4></section>
  <section><p>A sound system is a set of devices used to produce and amplify audio. [2]</p></section>
</section>

<section>
  <section><h4><b>41) </b>State two uses of sound systems?</h4></section>
  <section><p>Playing music</p></section>
  <section><p>Enhancing television audio [2]</p></section>
</section>

<section>
  <section><h4><b>42) </b>Define the term media player?</h4></section>
  <section><p>A media player is a device used to play digital audio and video content. [2]</p></section>
</section>

<section>
  <section><h4><b>43) </b>Explain how media players stream content?</h4></section>
  <section><p>Data is received in small chunks.</p></section>
  <section><p>Data is buffered temporarily.</p></section>
  <section><p>Playback starts before full download.</p></section>
  <section><p>This allows immediate viewing. [4]</p></section>
</section>

<section>
  <section><h4><b>44) </b>Define the term games console?</h4></section>
  <section><p>A games console is a digital device designed primarily for playing video games. [2]</p></section>
</section>

<section>
  <section><h4><b>45) </b>State two features of games consoles?</h4></section>
  <section><p>Wireless controllers</p></section>
  <section><p>Internet connectivity [2]</p></section>
</section>

<section>
  <section><h4><b>46) </b>Explain how virtual reality enhances gaming?</h4></section>
  <section><p>It immerses players in a 3D environment.</p></section>
  <section><p>Head and motion tracking improve interaction.</p></section>
  <section><p>This increases realism.</p></section>
  <section><p>Player engagement is improved. [4]</p></section>
</section>

<section>
  <section><h4><b>47) </b>Define the term navigation aid?</h4></section>
  <section><p>A navigation aid provides directions and location information. [2]</p></section>
</section>

<section>
  <section><h4><b>48) </b>Explain how GPS works?</h4></section>
  <section><p>GPS receives signals from satellites.</p></section>
  <section><p>Time delays are used for triangulation.</p></section>
  <section><p>The position is calculated.</p></section>
  <section><p>Accurate directions are provided. [4]</p></section>
</section>

<section>
  <section><h4><b>49) </b>Define the term smart home device?</h4></section>
  <section><p>A smart home device connects to a home network and can be remotely controlled. [2]</p></section>
</section>

<section>
  <section><h4><b>50) </b>State two examples of smart assistants?</h4></section>
  <section><p>Amazon Alexa</p></section>
  <section><p>Google Assistant [2]</p></section>
</section>

<section>
  <section><h4><b>51) </b>Explain one benefit of smart home devices?</h4></section>
  <section><p>They allow remote control of appliances.</p></section>
  <section><p>This improves convenience and efficiency. [3]</p></section>
</section>

<section>
  <section><h4><b>52) </b>Define the term multifunctional device?</h4></section>
  <section><p>A multifunctional device performs several different functions. [2]</p></section>
</section>

<section>
  <section><h4><b>53) </b>Explain why smartphones are multifunctional?</h4></section>
  <section><p>They combine communication and internet access.</p></section>
  <section><p>They support media, gaming and photography.</p></section>
  <section><p>Apps provide multiple functions.</p></section>
  <section><p>This replaces many separate devices. [4]</p></section>
</section>

<section>
  <section><h4><b>54) </b>Define the term convergence?</h4></section>
  <section><p>Convergence is combining multiple technologies into one device. [2]</p></section>
</section>

<section>
  <section><h4><b>55) </b>Explain how convergence affects digital devices?</h4></section>
  <section><p>Multiple functions are combined.</p></section>
  <section><p>Fewer separate devices are needed.</p></section>
  <section><p>Convenience is increased.</p></section>
  <section><p>Portability and cost efficiency improve. [4]</p></section>
</section>

<section>
  <section><h4><b>56) </b>Define the term portability?</h4></section>
  <section><p>Portability refers to how easily a device can be carried and used. [2]</p></section>
</section>

<section>
  <section><h4><b>57) </b>Explain why portability is important?</h4></section>
  <section><p>Devices can be used anywhere.</p></section>
  <section><p>Supports remote working.</p></section>
  <section><p>Improves convenience.</p></section>
  <section><p>Increases productivity. [4]</p></section>
</section>

<section>
  <section><h4><b>58) </b>Define the term performance?</h4></section>
  <section><p>Performance refers to how efficiently a device processes tasks. [2]</p></section>
</section>

<section>
  <section><h4><b>59) </b>Explain how RAM affects performance?</h4></section>
  <section><p>More RAM allows more programs to run.</p></section>
  <section><p>Data access is faster.</p></section>
  <section><p>Multitasking improves.</p></section>
  <section><p>Overall performance increases. [4]</p></section>
</section>

<section>
  <section><h4><b>60) </b>Define the term storage?</h4></section>
  <section><p>Storage is used to save data and programs. [2]</p></section>
</section>

<section>
  <section><h4><b>61) </b>State two types of storage?</h4></section>
  <section><p>Primary storage</p></section>
  <section><p>Secondary storage [2]</p></section>
</section>

<section>
  <section><h4><b>62) </b>Explain the difference between primary and secondary storage?</h4></section>
  <section><p>Primary storage is volatile and fast.</p></section>
  <section><p>It stores active data.</p></section>
  <section><p>Secondary storage is non-volatile.</p></section>
  <section><p>It stores data long-term. [4]</p></section>
</section>

<section>
  <section><h4><b>63) </b>State two types of user interface?</h4></section>
  <section><p>Graphical user interface</p></section>
  <section><p>Command-line interface [2]</p></section>
</section>

<section>
  <section><h4><b>64) </b>Describe the features of a GUI?</h4></section>
  <section><p>Uses windows.</p></section>
  <section><p>Uses icons and menus.</p></section>
  <section><p>Uses a pointer.</p></section>
  <section><p>Easy to use. [4]</p></section>
</section>

<section>
  <section><h4><b>65) </b>State one advantage and one disadvantage of CLI?</h4></section>
  <section><p>Uses fewer system resources.</p></section>
  <section><p>Commands must be memorised. [4]</p></section>
</section>

<section>
  <section><h4><b>66) </b>Define the term voice interface?</h4></section>
  <section><p>A voice interface allows control using spoken commands. [2]</p></section>
</section>

<section>
  <section><h4><b>67) </b>Explain one advantage of voice interfaces?</h4></section>
  <section><p>Allows hands-free operation.</p></section>
  <section><p>Improves accessibility and safety. [3]</p></section>
</section>

<section>
  <section><h4><b>68) </b>Define the term gesture interface?</h4></section>
  <section><p>A gesture interface allows control using hand or finger movements. [2]</p></section>
</section>

<section>
  <section><h4><b>69) </b>Explain how gesture interfaces are used?</h4></section>
  <section><p>Swiping scrolls content.</p></section>
  <section><p>Pinching zooms.</p></section>
  <section><p>Tapping selects items.</p></section>
  <section><p>This enables intuitive control. [4]</p></section>
</section>

<section>
  <section><h4><b>70) </b>Define the term connectivity?</h4></section>
  <section><p>Connectivity is the ability to connect to networks or devices. [2]</p></section>
</section>

<section>
  <section><h4><b>71) </b>State two advantages of wireless connectivity?</h4></section>
  <section><p>Mobility</p></section>
  <section><p>Reduced cabling [2]</p></section>
</section>

<section>
  <section><h4><b>72) </b>Explain one disadvantage of wireless connectivity?</h4></section>
  <section><p>Wireless signals can suffer interference.</p></section>
  <section><p>Security risks are higher. [3]</p></section>
</section>

<section>
  <section><h4><b>73) </b>Define the term media support?</h4></section>
  <section><p>Media support is the ability to handle different media formats. [2]</p></section>
</section>

<section>
  <section><h4><b>74) </b>Explain why multiple media formats are supported?</h4></section>
  <section><p>Ensures compatibility.</p></section>
  <section><p>Allows wider file access.</p></section>
  <section><p>Avoids file conversion.</p></section>
  <section><p>Improves convenience. [4]</p></section>
</section>

<section>
  <section><h4><b>75) </b>Define the term energy consumption?</h4></section>
  <section><p>Energy consumption is the amount of power used by a device. [2]</p></section>
</section>

<section>
  <section><h4><b>76) </b>Explain why low energy consumption is important?</h4></section>
  <section><p>Reduces electricity costs.</p></section>
  <section><p>Extends battery life.</p></section>
  <section><p>Supports mobile use.</p></section>
  <section><p>Reduces environmental impact. [4]</p></section>
</section>

<section>
  <section><h4><b>77) </b>Define the term security feature?</h4></section>
  <section><p>A security feature protects data and prevents unauthorised access. [2]</p></section>
</section>

<section>
  <section><h4><b>78) </b>State two software security features?</h4></section>
  <section><p>Password protection</p></section>
  <section><p>Encryption [2]</p></section>
</section>

<section>
  <section><h4><b>79) </b>Explain one software security feature?</h4></section>
  <section><p>Encryption converts data into unreadable format.</p></section>
  <section><p>Only authorised users can decrypt it.</p></section>
  <section><p>This prevents unauthorised access.</p></section>
  <section><p>Data remains protected. [4]</p></section>
</section>

<section>
  <section><h4><b>80) </b>State two physical security features?</h4></section>
  <section><p>Fingerprint scanner</p></section>
  <section><p>Security cable [2]</p></section>
</section>

<section>
  <section><h4><b>81) </b>Explain why physical security is important?</h4></section>
  <section><p>Portable devices are easily stolen.</p></section>
  <section><p>Physical security prevents unauthorised access.</p></section>
  <section><p>Protects sensitive data.</p></section>
  <section><p>Reduces financial and identity theft risk. [4]</p></section>
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

