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

	<meta name='description' content='Topic 1'>
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
					<h4>Topic 1</h4>
					<h2> Hardware & Software</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

	 <section>
  <section>
    <h4><b>1) </b>Define the term device.</h4>
  </section>
  <section>
    <p>A device is a piece of physical electronic equipment that is designed to perform one or more functions. In IT, a digital device normally contains hardware and software that work together to receive input, process data, store data and/or produce output. Examples include a smartphone, desktop computer, tablet, printer and games console.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>2) </b>Define the term feature of a device.</h4>
  </section>
  <section>
    <p>A feature is a characteristic or capability of a device that describes what it has or what it is able to provide. Examples include portability, storage capacity, processing speed, a touchscreen, wireless connectivity, biometric security and battery capacity. Features help users compare devices and decide whether a device is suitable for a particular purpose.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>3) </b>Define the term function of a device.</h4>
  </section>
  <section>
    <p>A function is the task or purpose that a device performs. For example, a smartphone can make telephone calls, send messages, access the internet, take photographs and run applications. A printer&#x27;s function is to produce a physical copy of digital information. The function describes what the device does rather than a physical characteristic it possesses.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>4) </b>Distinguish between a feature and a function, using examples.</h4>
  </section>
  <section>
    <p>A feature is a characteristic or capability of a device, whereas a function is the task the device performs. For example, a smartphone may have a 5000 mAh battery, GPS and a touchscreen as features. Its functions may include navigation, making calls and displaying information. Features describe the device; functions describe what it is used to do.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>5) </b>Identify three hardware features of a mobile phone.</h4>
  </section>
  <section>
    <p>Three hardware features are: (1) a touchscreen display for viewing information and entering input, (2) a camera for capturing photographs and video, and (3) a rechargeable battery that supplies electrical power. Other valid features include GPS, microphones, speakers, sensors, biometric scanners, storage and wireless communication hardware.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>6) </b>Identify three different functions of a mobile phone.</h4>
  </section>
  <section>
    <p>Three functions are communication, multimedia and internet access. A smartphone can make voice/video calls and send messages; it can capture, store and play photographs, video and audio; and it can access websites and online services using Wi-Fi or mobile data. Modern smartphones also provide navigation, payments, gaming and many other functions.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>7) </b>Explain why the main function of a device may differ between users.</h4>
  </section>
  <section>
    <p>The same device can be used for different purposes because users have different needs. For example, one person may mainly use a smartphone for communication, another for photography and another for gaming. The hardware and software may support all these functions, but the user&#x27;s requirements determine which function is most important.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>8) </b>Give two examples of software-based features in a modern device.</h4>
  </section>
  <section>
    <p>Examples include facial-recognition login and voice-assistant functionality. Facial recognition uses software algorithms to analyse biometric information and authenticate the user. A voice assistant processes spoken commands and can perform tasks such as searching for information, setting reminders or controlling other applications. These are software-based capabilities rather than purely physical hardware features.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>9) </b>Define hardware.</h4>
  </section>
  <section>
    <p>Hardware is the physical part of a computer or digital system that can be seen and physically handled. Examples include the processor, RAM, storage drive, motherboard, keyboard, mouse, monitor, printer and touchscreen. Hardware requires software to control it and make it perform useful tasks.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>10) </b>Identify four examples of internal hardware components.</h4>
  </section>
  <section>
    <p>Four examples are the central processing unit (CPU), random access memory (RAM), motherboard and internal storage such as an SSD or HDD. Other internal components include the power supply, graphics processing unit, cooling system and network interface hardware.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>11) </b>Identify four examples of external hardware components.</h4>
  </section>
  <section>
    <p>Four examples are a keyboard, mouse, monitor and printer. External hardware is normally connected to the computer through ports or wireless connections and provides input, output, communication or additional functionality.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>12) </b>Explain why hardware is essential for interacting with software.</h4>
  </section>
  <section>
    <p>Software consists of instructions, but those instructions need hardware to be executed. The processor executes program instructions, RAM holds data and instructions currently being used, storage holds software and data, and input/output devices allow users to interact with programs. Therefore, software and hardware work together as a complete digital system.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>13) </b>Define portability.</h4>
  </section>
  <section>
    <p>Portability is the degree to which a device can be easily transported and used in different locations. A highly portable device is usually small, light and designed to operate without a permanent connection to mains power or fixed equipment. Smartphones and tablets are highly portable compared with desktop PCs.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>14) </b>Explain why smaller devices are usually more portable.</h4>
  </section>
  <section>
    <p>Smaller devices generally have lower mass and occupy less physical space, so they are easier to carry, store and use in different locations. Miniaturisation also allows components such as processors, memory and storage to be integrated into compact devices. However, making a device smaller can create limitations such as reduced cooling, battery capacity or expandability.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>15) </b>Identify two advantages of making a device very small.</h4>
  </section>
  <section>
    <p>Two advantages are improved portability and reduced space requirements. A smaller device is easier to carry and can be used in locations where a larger device would be inconvenient. It can also fit into products such as smartwatches, medical devices and embedded systems where available physical space is limited.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>16) </b>Identify two disadvantages of making a device very small.</h4>
  </section>
  <section>
    <p>Two disadvantages are reduced space for components and potentially reduced expandability. Smaller devices may have less room for large batteries, cooling systems, ports or removable components. Repairs and upgrades can also be more difficult because components may be tightly integrated.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>17) </b>Explain why portability is important for mobile phones.</h4>
  </section>
  <section>
    <p>Mobile phones are designed to be carried and used in many locations. High portability allows a user to communicate, access information, use applications, take photographs and perform other tasks while travelling. A device that is too large or dependent on fixed equipment would not meet the main requirements of a mobile phone.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>18) </b>Explain why portability is less important for desktop computers.</h4>
  </section>
  <section>
    <p>Desktop computers are normally designed to remain in one location, such as an office, classroom or home. They can therefore use larger components, external monitors, full-size keyboards, powerful cooling systems and mains electricity. Performance, storage, expandability and connectivity may be more important than low weight.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>19) </b>State whether portability is important for each of the following and justify your answer: Keyboard, Mouse, SD card, Printer.</h4>
  </section>
  <section>
    <p>Keyboard – moderately important: it may need to be moved but is normally used at a workstation. Mouse – moderately important for the same reason. SD card – very important because it is designed to be small, removable and transported between compatible devices. Printer – generally less important because printers are normally positioned in a fixed location; size, output quality, speed and connectivity may be more important.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>20) </b>Define performance.</h4>
  </section>
  <section>
    <p>Performance is how effectively and efficiently a digital device performs its required tasks. It can be assessed using factors such as processing speed, capacity, bandwidth, throughput, latency, power efficiency and responsiveness. The importance of each factor depends on what the device is intended to do.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>21) </b>Explain what is meant by poor device performance.</h4>
  </section>
  <section>
    <p>Poor performance means that a device does not complete tasks efficiently or responds too slowly for its intended use. Examples include applications taking a long time to open, slow processing, low frame rates, delays when transferring data or insufficient capacity. Poor performance can result from inadequate hardware, insufficient memory, slow storage, network limitations or unsuitable software.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>22) </b>Describe how poor performance affects user experience.</h4>
  </section>
  <section>
    <p>Poor performance causes delays and makes a device less responsive. Users may experience applications freezing, slow loading, dropped frames, long file-transfer times or delays when entering commands. This can reduce productivity and make the device frustrating to use. In systems such as gaming or real-time control, poor performance may also prevent the device from carrying out its intended function correctly.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>23) </b>Explain why performance is more important for some devices than others.</h4>
  </section>
  <section>
    <p>Different devices have different workloads and requirements. A supercomputer or video-editing workstation must process very large amounts of data quickly, so processing speed, memory, storage and bandwidth are critical. A simple printer or basic sensor may perform a limited task and therefore does not require the same level of processing performance. Performance must be appropriate for the intended use.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>24) </b>Identify a device where high performance is essential and explain why.</h4>
  </section>
  <section>
    <p>A supercomputer requires very high performance because it is used for computationally intensive tasks such as scientific modelling, weather forecasting and complex simulations. Large numbers of processor cores, high memory capacity and high-speed data movement allow it to process enormous workloads in a reasonable time.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>25) </b>Explain whether a speaker requires high performance.</h4>
  </section>
  <section>
    <p>A speaker does not normally require the same processing performance as a high-end computer because its main task is to convert an audio signal into sound. However, suitable performance is still required to process the audio signal at the required quality and without noticeable delay. For advanced speakers, digital signal processing may require additional processing capability.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>26) </b>Explain whether a monitor requires high performance.</h4>
  </section>
  <section>
    <p>A monitor does not normally require high CPU performance because it mainly receives a video signal and displays it. However, its display performance can be important. Resolution, refresh rate, response time and colour accuracy affect the quality of the displayed output. A gaming monitor, for example, needs suitable refresh and response characteristics.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>27) </b>Evaluate the performance needs of a train station PC.</h4>
  </section>
  <section>
    <p>A train-station PC used for ticketing, timetable information or administration needs reliable and responsive performance rather than extreme processing power. It should have enough RAM and storage to run its software smoothly, a suitable processor, dependable network connectivity and good availability. If it displays live information or processes transactions, low latency and reliable connectivity are important. Excessive performance would add cost without necessarily providing useful benefits.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>28) </b>Define storage.</h4>
  </section>
  <section>
    <p>Storage is the ability to retain digital data and software so that the information can be used again later. Storage is normally non-volatile, meaning data remains stored when power is removed. Examples include HDDs, SSDs, memory cards, optical media, magnetic tape and network-attached storage.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>29) </b>Identify three devices that contain storage.</h4>
  </section>
  <section>
    <p>Three examples are a smartphone, desktop computer and digital camera. Smartphones commonly use internal flash storage, desktop computers may use SSDs or HDDs, and digital cameras commonly store photographs on internal memory or removable SD cards.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>30) </b>Name three different types of storage devices.</h4>
  </section>
  <section>
    <p>Three types are magnetic storage such as an HDD, solid-state storage such as an SSD, and optical storage such as a DVD or Blu-ray disc. Each uses a different technology for storing digital information.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>31) </b>Define magnetic storage.</h4>
  </section>
  <section>
    <p>Magnetic storage stores digital data by changing the magnetic state of a storage medium. Hard disk drives use magnetic platters, while magnetic tape uses a coated tape. Magnetic storage can provide large capacities at relatively low cost, but mechanical magnetic devices such as HDDs contain moving parts and can be slower and less resistant to physical shock than SSDs.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>32) </b>Define solid-state storage.</h4>
  </section>
  <section>
    <p>Solid-state storage stores data electronically using semiconductor memory and has no moving mechanical parts. SSDs and SD cards are examples. It generally provides fast access, low power consumption and good resistance to physical shock, making it suitable for portable devices.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>33) </b>Compare HDD and SSD storage.</h4>
  </section>
  <section>
    <p>An HDD stores data magnetically on rotating platters and uses moving read/write heads. An SSD stores data electronically in flash memory and has no moving parts. SSDs are generally faster, quieter, more resistant to shock and more power efficient. HDDs can provide large capacities at relatively low cost. The appropriate choice depends on capacity, cost, performance and portability requirements.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>34) </b>Explain why solid-state storage is used in mobile devices.</h4>
  </section>
  <section>
    <p>Solid-state storage has no moving parts, so it is more resistant to movement and physical shock. It is compact, generally fast and uses relatively little power. These properties make flash storage suitable for smartphones, tablets, cameras and other portable devices where space, battery life and durability are important.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>35) </b>Explain why large storage capacity may reduce portability.</h4>
  </section>
  <section>
    <p>Very large storage systems may require larger physical drives, additional power, cooling or external enclosures. A large external storage unit can add weight and bulk, making a system less convenient to carry. However, modern solid-state technology can provide substantial capacity in small devices, so the effect depends on the storage technology used.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>36) </b>Explain why some computers use both SSD and HDD.</h4>
  </section>
  <section>
    <p>Using both can combine the advantages of the two technologies. An SSD can be used for the operating system and frequently used applications because it provides fast access. A larger HDD can be used for files and backups where high capacity at lower cost is important. This gives the system fast everyday performance while retaining substantial storage capacity.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>37) </b>Define RAID storage.</h4>
  </section>
  <section>
    <p>RAID (Redundant Array of Independent Disks) is a method of combining multiple physical storage drives so that data is distributed and/or duplicated according to a chosen RAID level. RAID can be used to improve performance, provide redundancy, or both, depending on the configuration.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>38) </b>Explain how RAID storage may improve performance.</h4>
  </section>
  <section>
    <p>Some RAID levels distribute data across multiple drives, allowing more than one drive to read or write parts of the data simultaneously. This can increase throughput compared with a single drive. The actual performance benefit depends on the RAID level, workload, controller and number and type of drives used.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>39) </b>Describe RAID 0.</h4>
  </section>
  <section>
    <p>RAID 0 uses striping: data is divided into blocks and distributed across two or more drives. This can improve read and write performance because multiple drives can operate in parallel. However, RAID 0 provides no redundancy. If one drive fails, the data in the array may be lost because parts of files are spread across the drives.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>40) </b>Describe RAID 1.</h4>
  </section>
  <section>
    <p>RAID 1 uses mirroring. The same data is written to two or more drives, so one drive contains a duplicate of the other. If one drive fails, the other can continue to provide the data. RAID 1 therefore improves fault tolerance, but usable capacity is reduced because duplicate copies are stored.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>41) </b>Compare RAID 0 and RAID 1.</h4>
  </section>
  <section>
    <p>RAID 0 focuses on performance through striping and provides no redundancy. RAID 1 focuses on redundancy through mirroring and can continue operating after a single drive failure in a two-drive mirror. RAID 0 uses the combined capacity more efficiently, while RAID 1 sacrifices usable capacity to keep duplicate data.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>42) </b>Explain why RAID is used in supercomputers.</h4>
  </section>
  <section>
    <p>Supercomputers process extremely large amounts of data and require high storage throughput and availability. RAID can distribute data across drives to improve performance and, in suitable RAID levels, provide redundancy if a drive fails. This reduces the risk of a single storage failure interrupting important computational work.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>43) </b>Define user interface.</h4>
  </section>
  <section>
    <p>A user interface (UI) is the means by which a person interacts with a digital system. It includes the controls, displays and methods used to enter commands and receive information. Examples include graphical user interfaces, command-line interfaces, touch interfaces and voice interfaces.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>44) </b>Identify two types of user interface.</h4>
  </section>
  <section>
    <p>Two types are a graphical user interface (GUI) and a command-line interface (CLI). A GUI uses visual elements such as windows, icons, menus and buttons. A CLI allows users to enter text commands to control the system.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>45) </b>Describe the user interface of a mouse.</h4>
  </section>
  <section>
    <p>A mouse is a pointing input device rather than a complete user interface by itself. It allows a user to control a pointer on a graphical interface. Common actions include moving the pointer, clicking, double-clicking, right-clicking and scrolling. These actions select, open, move or manipulate graphical objects.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>46) </b>Describe the user interface of a desktop computer.</h4>
  </section>
  <section>
    <p>A desktop computer commonly uses a graphical user interface controlled through devices such as a keyboard and mouse. The user interacts with windows, icons, menus, buttons, files and applications. The monitor provides visual output while the keyboard and mouse provide input. The interface is designed for detailed interaction and multitasking.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>47) </b>Explain why user interfaces should be simple.</h4>
  </section>
  <section>
    <p>A simple interface reduces the amount of learning required and makes common tasks easier to complete. Clear controls, consistent layouts and understandable feedback reduce user errors and improve efficiency. Simplicity is particularly important when users have limited technical experience or when a device must be operated quickly.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>48) </b>Describe the user interface of each of the following: Keyboard, Mobile phone, Smart watch, Smart TV.</h4>
  </section>
  <section>
    <p>Keyboard – physical keys provide text and command input. Mobile phone – mainly a touchscreen GUI using icons, menus, gestures and an on-screen keyboard, often supported by voice input. Smart watch – a small touchscreen with gestures, buttons/crown controls and simplified applications because screen space is limited. Smart TV – usually a GUI controlled by a remote, buttons, voice control or a connected mobile device.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>49) </b>Define connectivity.</h4>
  </section>
  <section>
    <p>Connectivity is the ability of a device to connect to other devices, networks or services so that data can be exchanged. It may be wired, using technologies such as USB or Ethernet, or wireless, using technologies such as Wi-Fi, Bluetooth, NFC or mobile networks.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>50) </b>Define wired connection.</h4>
  </section>
  <section>
    <p>A wired connection transfers data through a physical cable or conductor between devices. Examples include USB, Ethernet, HDMI and fibre-optic connections. Wired connections can provide reliable communication and, depending on the technology, high bandwidth and low latency.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>51) </b>Define wireless connection.</h4>
  </section>
  <section>
    <p>A wireless connection transfers data without a physical data cable, normally using radio, microwave, infrared or other electromagnetic signals. Examples include Wi-Fi, Bluetooth, NFC and cellular networks. Wireless connections provide mobility but can be affected by interference, range and security considerations.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>52) </b>Identify types of wired cables.</h4>
  </section>
  <section>
    <p>Examples include twisted-pair Ethernet cables, coaxial cables, fibre-optic cables, USB cables and HDMI cables. The appropriate cable depends on the required purpose, such as networking, peripheral connection or digital audio/video transmission.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>53) </b>Define port.</h4>
  </section>
  <section>
    <p>A port is a physical or logical connection point through which a device can communicate with another device or system. Examples of physical ports include USB, HDMI and Ethernet. A port provides an interface for transmitting data, signals or power, depending on the technology.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>54) </b>Define WNIC.</h4>
  </section>
  <section>
    <p>A WNIC (Wireless Network Interface Card/Controller) is hardware that allows a computer or other device to connect to a wireless network. It provides the wireless communication capability required for technologies such as Wi-Fi.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>55) </b>Define Bluetooth chip.</h4>
  </section>
  <section>
    <p>A Bluetooth chip is an electronic component that provides Bluetooth wireless communication. It enables compatible devices to exchange data over short distances, for example connecting a phone to headphones, a keyboard, mouse or car system.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>56) </b>Define NFC.</h4>
  </section>
  <section>
    <p>NFC (Near-Field Communication) is a short-range wireless communication technology that allows compatible devices or tags to exchange small amounts of data when they are brought very close together. It is commonly used for contactless payments, access systems and quick device pairing.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>57) </b>Explain how wireless connections affect device size.</h4>
  </section>
  <section>
    <p>Wireless connectivity can reduce the need for physical connectors and long cables, allowing devices to be designed with fewer ports and less external cabling. However, the wireless hardware, antennas and supporting electronics still require physical space. Therefore, wireless technology can contribute to compact designs but does not remove all hardware requirements.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>58) </b>Explain how connectivity affects device performance.</h4>
  </section>
  <section>
    <p>Connectivity affects how quickly and reliably data can move between a device and other systems. Higher bandwidth can increase the amount of data transferred per second, while low latency reduces delays. Interference, weak signals, network congestion and packet loss can reduce effective performance. For online gaming, video conferencing and cloud services, suitable connectivity is particularly important.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>59) </b>Compare HDMI and VGA cables.</h4>
  </section>
  <section>
    <p>HDMI is a digital interface that can carry digital video and audio through one cable. VGA is an older analogue video interface and normally carries video only, requiring separate audio connections. HDMI generally supports modern high-resolution digital displays and simpler audio/video connections, while VGA is mainly found on older equipment.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>60) </b>Compare USB 2 and USB 3.</h4>
  </section>
  <section>
    <p>USB 3 provides substantially higher theoretical data-transfer rates than USB 2 and is therefore better suited to high-speed storage and large file transfers. USB 3 devices can also support improved power delivery depending on the specific standard. Compatibility is generally maintained, but the connection operates at the capabilities of the slower component or standard involved.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>61) </b>Explain the purpose of USB-C.</h4>
  </section>
  <section>
    <p>USB-C is a small, reversible connector design used for data transfer, charging and, where supported, display output. Its reversible shape makes it easier to plug in. USB-C itself describes the connector rather than one single speed or capability; the supported functions depend on the USB and other standards implemented by the device and cable.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>62) </b>Identify two advantages of USB-C.</h4>
  </section>
  <section>
    <p>Two advantages are its reversible connector, which makes connection easier, and its ability to support multiple uses such as data transfer and charging. On compatible systems it can also carry video signals and higher levels of power. This allows one connector type to replace several different physical connections.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>63) </b>Explain why Apple uses a Lightning connector.</h4>
  </section>
  <section>
    <p>Apple used Lightning on many products because it provided a compact, reversible connector for charging, data transfer and accessory connection. It allowed a relatively small physical connector to support several functions. Some newer Apple devices have moved to USB-C, so Lightning is not universal across current Apple products.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>64) </b>Define storage media.</h4>
  </section>
  <section>
    <p>Storage media is the physical or electronic medium on which digital data is recorded and retained. Examples include magnetic disk platters, magnetic tape, optical discs and semiconductor flash memory used in SSDs and memory cards.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>65) </b>Explain why media support is important for portable devices.</h4>
  </section>
  <section>
    <p>Portable devices need to work with the types of media and files required by their users. Support for removable storage, cameras, audio/video formats or wireless media allows users to access and transfer information conveniently. Compatibility can reduce the need for additional adapters or devices.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>66) </b>Describe the use of SD cards in mobile phones.</h4>
  </section>
  <section>
    <p>An SD or microSD card provides removable flash storage for compatible devices. It can be used to store photographs, videos, music and other files and can increase available storage capacity. It can also make it easier to move data between compatible devices. Not all modern phones include a removable card slot.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>67) </b>Explain why optical drives are less common in modern computers.</h4>
  </section>
  <section>
    <p>Software, music and video are increasingly distributed through downloads, streaming and cloud services. Solid-state storage and USB devices are also convenient alternatives. Optical drives take physical space and optical media are slower and less convenient for many modern uses. Consequently, many laptops and some desktop computers are manufactured without optical drives.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>68) </b>Identify two disadvantages of optical disks.</h4>
  </section>
  <section>
    <p>Optical disks generally have lower capacity and slower access than modern SSDs, and they can be scratched or damaged. They also require an optical drive, which is not present in many modern portable computers. These limitations reduce their convenience for frequent high-volume data storage.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>69) </b>Identify three types of media formats supported by devices.</h4>
  </section>
  <section>
    <p>Three broad media formats are audio, image and video. For example, devices may support audio formats such as MP3, image formats such as JPEG and video formats such as MP4. The exact formats supported depend on the device and its software.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>70) </b>Explain how cloud storage may reduce the need for media support.</h4>
  </section>
  <section>
    <p>Cloud storage allows files to be stored remotely and accessed through an internet connection. A device may therefore not need large removable storage media or optical drives for every file. Users can stream or download content when required. However, internet access, account availability, storage limits and privacy/security considerations still matter.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>71) </b>Define energy consumption.</h4>
  </section>
  <section>
    <p>Energy consumption is the amount of electrical energy used by a device while operating over a period of time. It depends on factors such as the components being used, workload, display brightness, network activity and power-management settings.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>72) </b>Explain why low energy consumption is important.</h4>
  </section>
  <section>
    <p>Low energy consumption can extend battery life in portable devices, reduce electricity costs and reduce the amount of heat produced. For organisations using many devices, improved energy efficiency can reduce operating costs and environmental impact. Energy efficiency is therefore an important design consideration for many digital systems.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>73) </b>Identify two disadvantages of high energy consumption.</h4>
  </section>
  <section>
    <p>High energy consumption increases electricity use and operating costs. It can also produce more heat, requiring additional cooling and potentially affecting component life. In battery-powered devices, high consumption reduces the time the device can operate between charges.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>74) </b>Explain how energy consumption affects battery life.</h4>
  </section>
  <section>
    <p>A battery stores a limited amount of energy. If a device consumes energy at a higher rate, the stored energy is depleted more quickly and the battery requires recharging sooner. Power-efficient components and software can reduce the rate of energy use and therefore increase operating time between charges.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>75) </b>Define expansion capability.</h4>
  </section>
  <section>
    <p>Expansion capability is the ability to add or upgrade hardware or functionality after a device has been purchased. Examples include adding RAM, installing additional storage, connecting expansion cards or attaching external peripherals. Expansion capability allows a system to adapt to changing requirements.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>76) </b>Explain why expansion capability is important to users.</h4>
  </section>
  <section>
    <p>Users&#x27; requirements can change over time. Expansion allows them to increase storage, memory, connectivity or other capabilities without replacing the entire device. This can extend the useful life of equipment and allow an organisation to upgrade systems as workloads increase.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>77) </b>Give two examples of devices with expansion capability.</h4>
  </section>
  <section>
    <p>A desktop PC can usually be expanded with additional RAM, storage drives, graphics cards and expansion cards. A compatible NAS device can often be expanded by adding or replacing storage drives. Other examples include some laptops, servers and single-board computer systems with expansion interfaces.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>78) </b>Explain how expansion capability benefits a desktop PC.</h4>
  </section>
  <section>
    <p>A desktop PC usually has accessible internal components and expansion slots. Users can add RAM, storage, graphics hardware, network adapters or other cards as requirements change. This allows the computer to be upgraded for new software or workloads without replacing the complete system.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>79) </b>Explain how expansion capability benefits NAS devices.</h4>
  </section>
  <section>
    <p>NAS devices can often be expanded by adding or replacing storage drives, increasing the amount of data that can be stored. Depending on the NAS, expansion may also support RAID changes, additional network features or external storage. This allows storage capacity to grow as an organisation&#x27;s data requirements increase.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>80) </b>Define security feature.</h4>
  </section>
  <section>
    <p>A security feature is a hardware or software characteristic designed to protect a device, its data or its users from unauthorised access, misuse, loss or theft. Examples include passwords, biometric authentication, encryption, secure boot, access controls and physical locks.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>81) </b>Explain why data security is important.</h4>
  </section>
  <section>
    <p>Data can contain personal, financial, business or confidential information. If unauthorised people access, alter or destroy it, individuals or organisations may suffer financial loss, privacy breaches, disruption or reputational damage. Security measures such as authentication, access controls, encryption and backups reduce these risks.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>82) </b>Define biometric data.</h4>
  </section>
  <section>
    <p>Biometric data is measurable information about a person&#x27;s physical or behavioural characteristics that can be used for identification or authentication. Examples include a fingerprint pattern, facial characteristics, iris pattern or voice characteristics.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>83) </b>Define biometric device.</h4>
  </section>
  <section>
    <p>A biometric device is hardware that captures or reads biometric characteristics and supplies the resulting data to a system for identification or authentication. Examples include fingerprint scanners, facial-recognition cameras and iris scanners.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>84) </b>Give three examples of biometric security.</h4>
  </section>
  <section>
    <p>Examples are fingerprint recognition, facial recognition and iris recognition. Other valid examples include voice recognition and some forms of palm or vein recognition. Each method uses a characteristic associated with the user to verify identity.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>85) </b>Explain why biometric security is more secure than passwords.</h4>
  </section>
  <section>
    <p>Biometric authentication can be harder to share or guess because it uses a physical or behavioural characteristic of the user. A password can be disclosed, reused or stolen. However, biometrics are not automatically more secure in every situation: biometric data must be protected, and a strong security system may combine biometrics with another authentication factor.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>86) </b>Describe the use of chip and PIN.</h4>
  </section>
  <section>
    <p>A chip-and-PIN payment card contains an embedded chip that stores and processes payment information. When a customer inserts the card into a compatible terminal, the chip communicates with the terminal and the customer enters a personal identification number (PIN). The PIN provides an additional authentication factor before the transaction is authorised.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>87) </b>Describe the use of RFID tags.</h4>
  </section>
  <section>
    <p>RFID tags use radio-frequency signals to allow stored identification data to be read by a compatible reader. They can be attached to products, access cards, assets or other objects. RFID can be used for stock tracking, access control, identification and automated data collection.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>88) </b>Describe the use of NFC in payments.</h4>
  </section>
  <section>
    <p>In a contactless payment, an NFC-enabled phone, card or wearable communicates with a compatible payment terminal over a very short distance. Payment credentials or a secure token are exchanged and the transaction is processed by the payment system. The short range reduces the need for physical contact between the device and terminal.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>89) </b>Define GPS.</h4>
  </section>
  <section>
    <p>GPS (Global Positioning System) is a satellite-based positioning system that allows a compatible receiver to calculate its location, usually using signals from multiple satellites. It can provide information such as latitude, longitude and, depending on the system and conditions, altitude and time.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>90) </b>Explain how GPS affects device design.</h4>
  </section>
  <section>
    <p>Adding GPS requires a receiver, antenna and supporting electronics, and software must process positioning data. This can increase component requirements and energy consumption. However, GPS enables functions such as navigation, location-aware services, tracking and geotagging, so manufacturers may accept these design costs when the functionality is required.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>91) </b>Identify two uses of GPS.</h4>
  </section>
  <section>
    <p>Two uses are satellite navigation, where the device determines a route or position, and location tracking, where the position of a person, vehicle or asset is recorded. GPS is also used for geotagging photographs, fitness tracking, emergency services and location-based applications.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>92) </b>Define touchscreen.</h4>
  </section>
  <section>
    <p>A touchscreen is a display that also detects touch input. It allows the user to interact directly with items displayed on the screen using taps, swipes, gestures or an on-screen keyboard. It combines output and input functions in the same physical interface.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>93) </b>Explain how touchscreens reduce device size.</h4>
  </section>
  <section>
    <p>A touchscreen can combine the functions of a display and several physical input controls. Instead of requiring a separate keyboard, keypad or many buttons, software controls can appear on the screen when needed. This reduces the amount of physical space required for input controls and can enable compact device designs.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>94) </b>Explain two advantages of touchscreens.</h4>
  </section>
  <section>
    <p>First, touchscreens can provide a flexible interface because the controls can change according to the application. Second, they allow direct interaction with displayed objects, which can be intuitive for many users. They also reduce the need for physical buttons and can support gestures such as pinch-to-zoom.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>95) </b>Define sensor.</h4>
  </section>
  <section>
    <p>A sensor is a device or component that detects a physical condition or change in the environment and converts it into a signal that a computer system can process. Examples include temperature, light, motion, pressure and acceleration sensors.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>96) </b>Describe the function of a light sensor.</h4>
  </section>
  <section>
    <p>A light sensor detects the intensity of surrounding light and produces a corresponding signal. A device can use this information to automatically adjust screen brightness, control lighting, detect environmental conditions or perform other automated actions.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>97) </b>Describe the function of an accelerometer.</h4>
  </section>
  <section>
    <p>An accelerometer measures acceleration along one or more axes. A device can use the readings to detect movement, orientation or changes in motion. For example, a smartphone can rotate its display when its orientation changes, while a fitness device can use acceleration data to estimate movement or steps.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>98) </b>Describe the function of a motion sensor.</h4>
  </section>
  <section>
    <p>A motion sensor detects movement or changes in position in an area. It can be used in security systems, automatic lighting, smart-home devices and other systems that need to respond when movement occurs.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>99) </b>Describe the function of a temperature sensor.</h4>
  </section>
  <section>
    <p>A temperature sensor measures temperature and provides a signal that can be processed by a computer system. It can be used to monitor equipment, control heating or cooling, protect hardware from overheating, or record environmental conditions.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>100) </b>Define RAM.</h4>
  </section>
  <section>
    <p>RAM (Random Access Memory) is volatile main memory used to hold data and program instructions that the processor is currently using. It provides fast access to active information. Its contents are normally lost when the device is powered off.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>101) </b>Define ROM.</h4>
  </section>
  <section>
    <p>ROM (Read-Only Memory) is non-volatile memory used to store data that should remain available when power is removed. In digital systems it can contain firmware or boot-related instructions. Modern systems may use forms of rewritable non-volatile memory, but the term ROM is still used for this role.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>102) </b>Compare RAM and ROM.</h4>
  </section>
  <section>
    <p>RAM is volatile and is used for temporary working data and programs currently in use. ROM is non-volatile and is used to retain important instructions or firmware when power is removed. RAM is generally designed for frequent read/write access, while ROM is primarily used to retain persistent instructions.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>103) </b>Explain how RAM affects performance.</h4>
  </section>
  <section>
    <p>More RAM allows a system to keep more active programs and data in fast main memory. If RAM is insufficient, the operating system may need to use slower storage as virtual memory, causing increased delays. Increasing RAM can therefore improve multitasking and responsiveness when the original system was constrained by insufficient memory.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>104) </b>Explain how battery size affects portability.</h4>
  </section>
  <section>
    <p>A larger battery can store more energy and may provide longer operating time, but it can also add weight and physical size. A smaller battery improves compactness and reduces weight but may provide shorter battery life. Designers therefore balance battery capacity against portability and expected usage.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>105) </b>Explain the benefits of solid-state batteries.</h4>
  </section>
  <section>
    <p>Solid-state batteries use a solid electrolyte rather than a conventional liquid electrolyte. Potential benefits include improved energy density, reduced risk of leakage and potentially improved safety and durability, depending on the technology. They may allow future devices to provide longer operating times or smaller designs, although practical performance varies by implementation.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>106) </b>Define miniaturisation.</h4>
  </section>
  <section>
    <p>Miniaturisation is the process of making electronic components and complete digital devices smaller while retaining the required functionality. Advances in semiconductor manufacturing and integration allow more processing, memory and communication capability to be placed in a smaller physical space.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>107) </b>Explain how ICs support miniaturisation.</h4>
  </section>
  <section>
    <p>An integrated circuit (IC) places many electronic components, such as transistors, onto a small semiconductor chip. Instead of using many separate physical components, functions can be integrated into a compact package. This reduces physical size, wiring requirements and often power consumption, supporting smaller digital devices.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>108) </b>Explain why miniaturisation is important for embedded systems.</h4>
  </section>
  <section>
    <p>Embedded systems are often built into products where physical space is limited, such as vehicles, appliances, medical devices and wearable technology. Smaller processors, memory and sensors allow the required computing functions to fit inside the host product without taking excessive space or adding unnecessary weight.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>109) </b>Define processor.</h4>
  </section>
  <section>
    <p>A processor, or CPU, is the component that executes program instructions and performs calculations and logical operations. It fetches instructions, decodes them and executes them, working with memory and other hardware to operate the computer system.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>110) </b>Define clock speed.</h4>
  </section>
  <section>
    <p>Clock speed is the frequency at which a processor&#x27;s clock operates, commonly measured in hertz such as gigahertz (GHz). It indicates how many clock cycles occur per second. Clock speed can affect performance, but it is not by itself a complete measure of processor performance because architecture, core count, cache and workload also matter.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>111) </b>Define core.</h4>
  </section>
  <section>
    <p>A processor core is an individual processing unit within a CPU that can execute instructions. A multi-core processor contains multiple cores, allowing it to execute multiple instruction streams or threads concurrently when software and the operating system can make effective use of them.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>112) </b>Explain how clock speed affects performance.</h4>
  </section>
  <section>
    <p>A higher clock speed can allow a processor to perform more clock cycles per second, potentially reducing the time needed for some tasks. However, performance also depends on processor architecture, instruction efficiency, number of cores, cache, memory speed and the workload. Therefore, two processors with the same clock speed do not necessarily have the same performance.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>113) </b>Explain how multiple cores affect performance.</h4>
  </section>
  <section>
    <p>Multiple cores allow a processor to work on multiple tasks or threads concurrently. When software is designed to use parallel processing, additional cores can improve performance and multitasking. The benefit depends on how well the workload can be divided; a single-threaded task may gain little from extra cores.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>114) </b>Define technological convergence.</h4>
  </section>
  <section>
    <p>Technological convergence is the combining of functions that were previously provided by separate technologies or devices into one device or integrated system. A smartphone is a common example because it combines communication, camera, GPS, media playback, computing, internet access and other functions.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>115) </b>Define Internet of Things (IoT).</h4>
  </section>
  <section>
    <p>The Internet of Things is a network of physical devices that contain sensors, processing capability and communication technology so that they can collect, exchange and/or act on data. Examples include smart thermostats, connected appliances, wearable devices and industrial monitoring systems.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>116) </b>Explain how IoT improves daily life.</h4>
  </section>
  <section>
    <p>IoT devices can automatically collect information and respond to conditions. For example, a smart thermostat can adjust heating according to temperature or a user&#x27;s settings, while a fitness tracker can record activity. Connected devices can provide automation, monitoring, alerts and remote control, potentially making tasks more convenient and efficient.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>117) </b>Explain how a smartphone is an example of technological convergence.</h4>
  </section>
  <section>
    <p>A smartphone combines several functions that historically required separate devices. It can act as a telephone, camera, GPS navigator, music player, video player, web browser, gaming device and portable computer. These capabilities are integrated into one compact device through combined hardware, software and connectivity.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>118) </b>Explain how a smart TV is an example of technological convergence.</h4>
  </section>
  <section>
    <p>A smart TV combines traditional television functions with computer and internet capabilities. It can display broadcast or streamed video, run applications, connect to online services and sometimes support gaming, video calls and smart-home control. Several previously separate technologies are therefore integrated into one device.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>119) </b>Define embedded system.</h4>
  </section>
  <section>
    <p>An embedded system is a computer system built into a larger product or device to perform a dedicated function or set of functions. It usually contains a processor, memory, software and interfaces to sensors or other hardware. Examples include systems in washing machines, cars, medical devices and appliances.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>120) </b>Identify four characteristics of embedded systems.</h4>
  </section>
  <section>
    <p>Typical characteristics are: a dedicated purpose; hardware and software designed for a specific product; limited resources such as memory, processing power or energy; and operation with little or no direct user intervention. Many embedded systems also need predictable or real-time responses.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>121) </b>Define microprocessor.</h4>
  </section>
  <section>
    <p>A microprocessor is a programmable integrated circuit that contains the main processing functions of a CPU on a single chip. It executes instructions and performs calculations and logical operations. Microprocessors are used in computers, embedded systems and many other digital devices.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>122) </b>Explain the role of a microprocessor in an embedded system.</h4>
  </section>
  <section>
    <p>The microprocessor executes the embedded software and processes inputs from sensors or other components. It makes decisions according to programmed instructions and controls outputs such as motors, displays, valves or alarms. It therefore provides the computational control needed for the embedded system to perform its dedicated function.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>123) </b>Identify five examples of embedded systems.</h4>
  </section>
  <section>
    <p>Examples include a washing-machine controller, car engine-control system, microwave oven controller, medical monitoring device and automatic traffic-light controller. Other examples include printers, routers, smart thermostats, digital cameras and many industrial control systems.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>124) </b>Compare an embedded system with a general-purpose computer.</h4>
  </section>
  <section>
    <p>An embedded system is normally designed for a specific function within a larger product and has a constrained set of resources. A general-purpose computer is designed to run many different applications and can be configured for many tasks. Embedded systems often prioritise size, cost, power efficiency, reliability and predictable operation, while general-purpose systems prioritise flexibility.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>125) </b>Define firmware.</h4>
  </section>
  <section>
    <p>Firmware is software stored in non-volatile memory that provides low-level control of hardware. It is closely associated with a device and allows the hardware to start and operate correctly. Firmware may be stored in ROM, EEPROM, flash memory or another suitable non-volatile storage technology.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>126) </b>Explain why firmware is essential.</h4>
  </section>
  <section>
    <p>Firmware provides the basic instructions required for hardware to initialise and operate. Without appropriate firmware, a device may not know how to control its hardware or start the higher-level software. Firmware therefore forms a bridge between the physical hardware and the operating system or application software.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>127) </b>Define BIOS.</h4>
  </section>
  <section>
    <p>BIOS (Basic Input/Output System) is firmware used on many traditional PCs to initialise hardware during startup and provide basic input/output services. It performs hardware checks and helps locate and start the operating system. Modern PCs commonly use UEFI firmware, which performs the corresponding modern boot functions.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>128) </b>Define bootloader.</h4>
  </section>
  <section>
    <p>A bootloader is a small program that starts during the boot process and loads or starts the operating system. It may initialise or configure required hardware and locate the operating system on storage before transferring control to it.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>129) </b>Describe the boot process of a computer.</h4>
  </section>
  <section>
    <p>When a computer is powered on, firmware starts and performs initial hardware checks and configuration. It identifies available boot devices and locates a bootloader. The bootloader loads the operating system kernel and transfers control to it. The operating system then initialises drivers, services and the user environment so the computer is ready for use.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>130) </b>Compare firmware in embedded systems and general-purpose computers.</h4>
  </section>
  <section>
    <p>In an embedded system, firmware is closely tailored to the specific hardware and dedicated function of the product and may be stored in non-volatile memory on the device. In a general-purpose computer, firmware such as UEFI initialises hardware and starts the operating system. Both provide low-level hardware control, but their purposes and complexity reflect the different system designs.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>131) </b>Identify factors used to assess device performance.</h4>
  </section>
  <section>
    <p>Important factors include speed, capacity, portability, bandwidth and power efficiency. Other practical measures can include latency, throughput, responsiveness and reliability. The importance of each factor depends on the intended use of the device.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>132) </b>Explain how processor speed affects performance.</h4>
  </section>
  <section>
    <p>Processor speed affects how quickly a CPU can execute instructions, particularly for workloads that depend heavily on processing. A faster processor can reduce processing time, but performance also depends on architecture, cores, cache, memory and software. Therefore processor speed should be considered together with the other characteristics of the system.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>133) </b>Explain how number of cores affects performance.</h4>
  </section>
  <section>
    <p>More processor cores allow multiple tasks or threads to be processed concurrently. This can improve multitasking and the performance of software designed for parallel processing. However, software that cannot use multiple cores may not benefit significantly, so increasing core count does not automatically make every task proportionally faster.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>134) </b>Explain how storage capacity affects performance.</h4>
  </section>
  <section>
    <p>Storage capacity mainly determines how much data and software can be stored, rather than directly determining processing speed. However, insufficient free storage can affect system operation and may force users to manage or move data. Storage technology also affects performance: SSDs generally provide faster access than HDDs, so capacity and storage type should be considered separately.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>135) </b>Explain how portability can be a performance factor.</h4>
  </section>
  <section>
    <p>Portability can be considered a performance factor when evaluating whether a device performs effectively in its intended environment. A mobile worker may require a device that is light enough to carry while still providing adequate battery life and processing capability. A device with excellent processing power but excessive weight may not meet the required practical performance.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>136) </b>Define bandwidth.</h4>
  </section>
  <section>
    <p>Bandwidth is the maximum rate at which a communication channel can carry data, usually measured in bits per second. A higher bandwidth can allow more data to be transferred in a given period, although actual throughput can be lower because of congestion, protocol overhead, interference and other limitations.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>137) </b>Explain how bandwidth affects device performance.</h4>
  </section>
  <section>
    <p>Higher network bandwidth can allow a device to transfer larger amounts of data more quickly. This can improve downloads, streaming, cloud access and file transfers. If bandwidth is too low, data transfers may take longer and services such as high-resolution video may experience buffering. Bandwidth is only one factor; latency and network quality also matter.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>138) </b>Explain how power efficiency affects performance.</h4>
  </section>
  <section>
    <p>Power efficiency describes how much useful work a device can perform for a given amount of energy. Efficient components can provide adequate performance while using less power, reducing heat and extending battery life. In portable devices, this can allow longer operation without sacrificing the required level of functionality.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>139) </b>Evaluate the suitability of a laptop, tablet and phone for a university student.</h4>
  </section>
  <section>
    <p>A laptop is generally suitable for substantial academic work because it provides a larger display, physical keyboard, multitasking capability and access to a wide range of software. A tablet is portable and useful for reading, note-taking and media, but may be less suitable for extensive typing or specialist software. A phone is highly portable and useful for communication and quick tasks, but its small display and input method can limit long academic work. The final choice depends on the student&#x27;s subjects, software requirements, budget and portability needs.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>140) </b>Recommend the best device for multimedia editing.</h4>
  </section>
  <section>
    <p>For demanding multimedia editing, a suitable high-performance desktop or laptop would normally be selected because editing requires substantial processor performance, RAM, fast storage and often dedicated graphics processing. A larger display and suitable ports can also help. The exact specification should be chosen according to the resolution, software and size of the projects rather than choosing a device solely by brand or price.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>141) </b>Evaluate devices for restaurant order-taking.</h4>
  </section>
  <section>
    <p>A tablet or dedicated touchscreen terminal is well suited because it is portable, easy to clean and can present a simple graphical ordering interface. A phone can also be used but has a smaller display. A desktop PC is less convenient for taking orders at tables because it is not easily portable. The system should also have reliable Wi-Fi or another network connection, adequate battery life and secure payment connectivity where required.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>142) </b>Justify the best device choice for a restaurant.</h4>
  </section>
  <section>
    <p>For table-side order-taking, a robust tablet or dedicated handheld terminal provides a practical combination of portability, touchscreen input, battery operation and connectivity. It allows staff to enter orders near the customer and transmit them to the restaurant&#x27;s ordering system. The final selection should also consider durability, hygiene, security, battery life, network reliability and compatibility with the restaurant&#x27;s software.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>143) </b>Define analogue data.</h4>
  </section>
  <section>
    <p>Analogue data is information represented by continuously varying physical quantities. Values can take any value within a range. Examples include sound waves, temperature and continuously varying electrical signals.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>144) </b>Define digital data.</h4>
  </section>
  <section>
    <p>Digital data is information represented using discrete values that can be stored and processed by digital systems. Computers commonly represent digital information using binary digits, 0 and 1.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>145) </b>Define binary.</h4>
  </section>
  <section>
    <p>Binary is a base-2 number system that uses only two digits: 0 and 1. Digital computers use binary representation because electronic circuits can reliably represent two states, such as off/on or low/high voltage.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>146) </b>Define denary.</h4>
  </section>
  <section>
    <p>Denary, also called decimal, is the base-10 number system used in everyday life. It uses the ten digits 0 to 9. Each position represents a power of ten.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>147) </b>Explain why computers use binary.</h4>
  </section>
  <section>
    <p>Digital electronic circuits can reliably distinguish between two states, such as a low and high voltage or off and on. Binary represents these two states using 0 and 1. This makes digital data storage and processing reliable and allows logic circuits to perform calculations and decisions.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>148) </b>Convert 7 to binary.</h4>
  </section>
  <section>
    <p>7 in denary is 111 in binary because 7 = 4 + 2 + 1. Therefore: 7₁₀ = 111₂.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>149) </b>Convert 10 to binary.</h4>
  </section>
  <section>
    <p>10 = 8 + 2, so the binary representation has 1s in the 8 and 2 positions. Therefore: 10₁₀ = 1010₂.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>150) </b>Convert 51 to binary.</h4>
  </section>
  <section>
    <p>51 = 32 + 16 + 2 + 1. Therefore the bits for 32, 16, 8, 4, 2 and 1 are 1,1,0,0,1,1. So 51₁₀ = 110011₂.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>151) </b>Convert 99 to binary.</h4>
  </section>
  <section>
    <p>99 = 64 + 32 + 2 + 1. Therefore the bits for 64, 32, 16, 8, 4, 2 and 1 are 1,1,0,0,0,1,1. So 99₁₀ = 1100011₂.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>152) </b>Convert 180 to binary.</h4>
  </section>
  <section>
    <p>180 = 128 + 32 + 16 + 4. Therefore the bits for 128, 64, 32, 16, 8, 4, 2 and 1 are 1,0,1,1,0,1,0,0. So 180₁₀ = 10110100₂.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>153) </b>Convert 256 to binary.</h4>
  </section>
  <section>
    <p>256 is 2⁸, so it is represented by a 1 followed by eight zeros: 256₁₀ = 100000000₂.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>154) </b>Explain the method used to convert denary to binary.</h4>
  </section>
  <section>
    <p>One method is repeated division by 2. Divide the denary number by 2 and record the remainder (0 or 1). Continue dividing the quotient by 2 until the quotient is 0. Read the remainders from bottom to top to obtain the binary number. Another method is to select powers of 2 from the largest to the smallest and subtract each value where possible.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>155) </b>Convert 0010 to denary.</h4>
  </section>
  <section>
    <p>Using binary place values 8, 4, 2 and 1: 0×8 + 0×4 + 1×2 + 0×1 = 2. Therefore 0010₂ = 2₁₀.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>156) </b>Convert 1001 to denary.</h4>
  </section>
  <section>
    <p>Using place values 8, 4, 2 and 1: 1×8 + 0×4 + 0×2 + 1×1 = 9. Therefore 1001₂ = 9₁₀.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>157) </b>Convert 1111 to denary.</h4>
  </section>
  <section>
    <p>Using place values 8, 4, 2 and 1: 1×8 + 1×4 + 1×2 + 1×1 = 15. Therefore 1111₂ = 15₁₀.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>158) </b>Convert 00111110 to denary.</h4>
  </section>
  <section>
    <p>Using place values 128, 64, 32, 16, 8, 4, 2 and 1: 0+0+32+16+8+4+2+0 = 62. Therefore 00111110₂ = 62₁₀.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>159) </b>Convert 10010000 to denary.</h4>
  </section>
  <section>
    <p>Using place values 128, 64, 32, 16, 8, 4, 2 and 1: 128 + 0 + 0 + 16 + 0 + 0 + 0 + 0 = 144. Therefore 10010000₂ = 144₁₀.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>160) </b>Explain the method used to convert binary to denary.</h4>
  </section>
  <section>
    <p>Write the binary digits above their place values, which are powers of 2. Starting from the right, the place values are 1, 2, 4, 8, 16, 32, 64, 128 and so on. Multiply each bit by its place value and add the results. The total is the denary value.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>161) </b>Define bit.</h4>
  </section>
  <section>
    <p>A bit is the smallest unit of digital data and can have one of two values, normally represented as 0 or 1. The term is short for binary digit.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>162) </b>Define byte.</h4>
  </section>
  <section>
    <p>A byte is a group of 8 bits. It is commonly used as a basic unit for measuring digital storage and data size. For example, one byte can represent a character in many common character-encoding systems such as ASCII.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>163) </b>Define nibble.</h4>
  </section>
  <section>
    <p>A nibble is a group of 4 bits. Since one byte contains 8 bits, one byte contains two nibbles.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>164) </b>Convert KiB to bytes.</h4>
  </section>
  <section>
    <p>Under the IEC binary units used by the Edexcel specification, 1 KiB = 2¹⁰ bytes = 1024 bytes. Therefore, to convert KiB to bytes, multiply the number of KiB by 1024. For example, 5 KiB = 5 × 1024 = 5120 bytes.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>165) </b>Convert GiB to TiB.</h4>
  </section>
  <section>
    <p>Using IEC binary units, 1 TiB = 1024 GiB. Therefore, to convert GiB to TiB, divide the number of GiB by 1024. For example, 2048 GiB = 2 TiB.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>166) </b>Distinguish between IEC and SI units.</h4>
  </section>
  <section>
    <p>IEC binary units use powers of 2, for example 1 KiB = 1024 bytes, 1 MiB = 1024 KiB and 1 GiB = 1024 MiB. SI decimal units use powers of 10, for example 1 kB = 1000 bytes, 1 MB = 1000 kB and 1 GB = 1000 MB. The Edexcel IAL specification explicitly requires binary/denary work using IEC units.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>167) </b>Calculate the size of a text file using ASCII.</h4>
  </section>
  <section>
    <p>With standard 7-bit ASCII, each character is represented by 7 bits; when stored in a full 8-bit byte, one character normally occupies 1 byte. Therefore, for a simple ASCII text file, size in bytes can be calculated as approximately the number of characters, including spaces and punctuation, multiplied by 1 byte per character. For example, 2,000 ASCII characters require approximately 2,000 bytes, excluding any additional file-system metadata.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>168) </b>Write an expression to calculate file transfer time.</h4>
  </section>
  <section>
    <p>Transfer time can be calculated using: transfer time (seconds) = file size (bits) ÷ data-transfer rate (bits per second). The file size must first be converted to bits if it is given in bytes. For example, a file of 10 MB contains 10 × 8 × 1,000,000 bits if decimal MB is specified.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>169) </b>Explain why bandwidth affects transfer time.</h4>
  </section>
  <section>
    <p>Bandwidth determines the maximum amount of data that can be transmitted per second. For a fixed file size, increasing the available bandwidth generally reduces the theoretical transfer time because more bits can be transmitted each second. Actual transfer time may be longer because of network congestion, protocol overhead, latency, interference and other limitations.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>170) </b>Write an expression to calculate transfer time over a 5 Gbps connection.</h4>
  </section>
  <section>
    <p>Use: transfer time (seconds) = file size (bits) ÷ 5,000,000,000. If the file size is given in bytes, first multiply the number of bytes by 8. For example, a file of 1 GB using decimal units contains 8,000,000,000 bits, so the theoretical minimum transfer time at 5 Gbps is 8,000,000,000 ÷ 5,000,000,000 = 1.6 seconds, ignoring overhead and other network limitations.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>171) </b>Define software.</h4>
  </section>
  <section>
    <p>Software is a set of programs, instructions and associated data that tells computer hardware what to do. Software cannot normally be physically handled in the same way as hardware. Examples include operating systems, web browsers, word processors, databases and device drivers.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>172) </b>Distinguish between systems software and application software.</h4>
  </section>
  <section>
    <p>Systems software manages or supports the operation of the computer itself. Examples include operating systems, device drivers and utility software. Application software is designed to help the user perform particular tasks, such as word processing, photo editing, web browsing or accounting. Applications depend on systems software to access hardware and system services.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>173) </b>Identify five examples of application software.</h4>
  </section>
  <section>
    <p>Examples include a word processor, spreadsheet application, database management application, web browser and image-editing application. Other valid examples include presentation software, video-editing software, email clients and computer-aided design software.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>174) </b>Identify five examples of systems software.</h4>
  </section>
  <section>
    <p>Examples include an operating system, device driver, utility program, firmware and system-management software. Examples of operating systems include Windows, Linux and macOS. Drivers allow the operating system to communicate with specific hardware.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>175) </b>Define operating system.</h4>
  </section>
  <section>
    <p>An operating system (OS) is systems software that manages computer hardware and software resources and provides services for application programs. It manages areas such as devices, processes, memory, users and security, and provides an interface through which users and applications can interact with the computer.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>176) </b>Identify roles of an operating system.</h4>
  </section>
  <section>
    <p>Key roles include managing hardware devices, managing processes, managing memory, managing users and permissions, providing security, managing files and storage, providing a user interface, and allocating system resources. The OS coordinates these activities so applications can operate without directly controlling every hardware component.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>177) </b>Explain how the OS manages devices.</h4>
  </section>
  <section>
    <p>The operating system uses device drivers and system services to communicate with hardware. It receives requests from applications, sends appropriate commands to devices and manages resources such as printers, keyboards, storage and displays. Drivers translate operating-system commands into instructions understood by particular hardware.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>178) </b>Explain the role of drivers.</h4>
  </section>
  <section>
    <p>A device driver is software that allows the operating system to communicate with a particular hardware device. It translates general operating-system requests into device-specific commands and handles communication between the OS and hardware. Without a suitable driver, an operating system may not be able to use a device correctly.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>179) </b>Define interrupt.</h4>
  </section>
  <section>
    <p>An interrupt is a signal that causes the processor to temporarily stop or suspend its current execution so that it can respond to an event requiring attention. The operating system or interrupt handler deals with the event and then the processor can return to the interrupted task.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>180) </b>Explain multitasking.</h4>
  </section>
  <section>
    <p>Multitasking is the ability of an operating system to manage multiple processes so that they can make progress apparently at the same time. The OS allocates processor time and other resources between processes. On a multi-core system, some tasks may genuinely execute simultaneously on different cores; on a single core, rapid switching creates the appearance of simultaneous execution.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>181) </b>Explain how the OS manages memory.</h4>
  </section>
  <section>
    <p>The operating system allocates RAM to processes, keeps track of which memory areas are in use and prevents processes from incorrectly accessing protected areas. It can also use virtual memory, where part of storage is used when physical RAM is insufficient. Effective memory management allows multiple applications to operate safely and efficiently.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>182) </b>Explain how the OS manages users.</h4>
  </section>
  <section>
    <p>The operating system can create user accounts, authenticate users and assign permissions. Different users can be given different levels of access to files, applications and system settings. This allows personalisation while helping prevent unauthorised users from changing protected data or system configurations.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>183) </b>Explain how the OS manages security.</h4>
  </section>
  <section>
    <p>The OS provides security mechanisms such as user authentication, permissions, access controls, secure system settings, process isolation and security updates. It can restrict which users and applications can access particular resources. These controls help protect data and hardware from unauthorised access and malicious activity.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>184) </b>Define free software.</h4>
  </section>
  <section>
    <p>In the context of software sources, free software is software made available at no monetary cost to the user. &#x27;Free&#x27; in this sense does not necessarily mean that the source code can be modified or redistributed; those rights depend on the software&#x27;s licence. This should be distinguished from open-source software.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>185) </b>Define open-source software.</h4>
  </section>
  <section>
    <p>Open-source software is software whose source code is made available under a licence that permits specified rights such as inspecting, modifying and redistributing the code. The exact permissions depend on the particular open-source licence. Open-source does not simply mean &#x27;free of charge&#x27;.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>186) </b>Define proprietary software.</h4>
  </section>
  <section>
    <p>Proprietary software is software controlled by an individual or organisation that retains ownership and determines the terms under which it may be used, copied, modified or distributed. The source code is normally not freely available for users to modify.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>187) </b>Compare free, open-source and proprietary software.</h4>
  </section>
  <section>
    <p>Free software in the worksheet&#x27;s sense is available without a purchase price, but its licence may still impose restrictions. Open-source software makes source code available under licence and provides defined rights to inspect, modify and/or redistribute it. Proprietary software is controlled by its owner and normally restricts copying, modification or redistribution. These categories can overlap in some cases, so the licence terms must be checked.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>188) </b>Explain Creative Commons licences.</h4>
  </section>
  <section>
    <p>Creative Commons licences provide standardised ways for copyright owners to give the public permission to use their work under specified conditions. Different licences may require attribution, restrict commercial use, require derivatives to use the same licence, or prohibit derivative works. Users must follow the conditions of the particular licence.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>189) </b>Describe a single-user licence.</h4>
  </section>
  <section>
    <p>A single-user licence permits software to be used by one specified user, or on one designated device depending on the licence terms. It is suitable when an individual needs the software for personal use. The exact rights, including transfer or installation on multiple devices, depend on the licence agreement.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>190) </b>Describe a multi-user licence.</h4>
  </section>
  <section>
    <p>A multi-user licence permits more than one user to use the software, usually subject to a specified number of users, installations or concurrent users. It can be suitable for small organisations where several people need access without purchasing a completely separate licence for every user.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>191) </b>Describe an institutional licence.</h4>
  </section>
  <section>
    <p>An institutional licence permits an organisation such as a school, college or business to use software across a defined group of users or devices. The agreement normally specifies the organisation covered, the permitted installations or users and the period and conditions of use.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>192) </b>Describe a fixed-term licence.</h4>
  </section>
  <section>
    <p>A fixed-term licence permits use of software for a specified period, such as one year. When the term ends, the organisation may need to renew the licence or stop using the software. Subscription-based software commonly uses this model.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>193) </b>Describe an indefinite licence.</h4>
  </section>
  <section>
    <p>An indefinite licence permits the user to continue using the licensed version of the software without a specified expiry date, subject to the licence conditions. It does not necessarily mean that the user receives all future upgrades or support; those may be governed by separate terms.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>194) </b>Describe a network licence.</h4>
  </section>
  <section>
    <p>A network licence permits software to be used across a network according to defined licensing conditions. It may allow a specified number of concurrent users or installations to access the software from networked computers. This can be useful in schools and organisations where users share a pool of software licences.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>195) </b>Define software patch.</h4>
  </section>
  <section>
    <p>A software patch is a small update designed to fix specific problems in existing software. A patch may correct security vulnerabilities, bugs or performance issues without representing a major new version of the software.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>196) </b>Define software upgrade.</h4>
  </section>
  <section>
    <p>A software upgrade is a newer version of software that introduces significant changes, improvements, new features or other substantial modifications compared with an earlier version. An upgrade may have different hardware or operating-system requirements and may be subject to a different licence or cost.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>197) </b>Explain automatic software updates.</h4>
  </section>
  <section>
    <p>Automatic updates allow software to download and install updates without requiring the user to manually search for each update. This can ensure that security patches and bug fixes are applied promptly. Organisations may control or schedule automatic updates to reduce disruption and to test compatibility before deployment.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>198) </b>Explain why software updates are released.</h4>
  </section>
  <section>
    <p>Updates may be released to fix bugs, close security vulnerabilities, improve performance, add features, improve compatibility with newer hardware or operating systems, and address other defects. Security updates are particularly important because attackers may exploit known vulnerabilities in outdated software.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>199) </b>Explain compatibility issues caused by updates.</h4>
  </section>
  <section>
    <p>An update can change software requirements, system libraries, drivers, file formats or application interfaces. As a result, older hardware or other software may no longer work correctly with the updated version. For example, an updated application may require a newer operating system or driver. Organisations should test important updates, check requirements and maintain backups or rollback procedures where appropriate.</p>
  </section>
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

