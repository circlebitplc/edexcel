<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title class="hightlight-blue">Enidu Batuwanthudawe</title>
    <meta name="description" content="IAL Computer Science - Unit 1 Topic 1 Computer Systems">
    <meta name="author" content="Enidu Batuwanthudawe">

    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <link rel="stylesheet" href="<?= $pptBase ?>/css/reveal.min.css">
    <link rel="stylesheet" href="<?= $pptBase ?>/css/theme/default.css" id="theme">
    <link rel="stylesheet" href="<?= $pptBase ?>/css/custom.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $pptBase ?>/lib/css/zenburn.css">

    <?php if (isset($_GET['print-pdf'])): ?>
    <link rel="stylesheet" href="<?= $pptBase ?>/css/print/pdf.css">
    <?php endif; ?>

    <!--[if lt IE 9]>
    <script src="<?= $pptBase ?>/lib/js/html5shiv.js"></script>
    <![endif]-->

    <script src="<?= $pptBase ?>/js/moment.min.js"></script>
</head>

<body>
<div class="reveal">
<div class="slides">

    <!-- TITLE -->
    <section>
        <h4>Unit 1 – Topic 1</h4>
        <h2>Computer Systems</h2>
        <?= ppt_teacher_credit_markup() ?>
    </section>

    <!-- 1 -->
    <section>
        <section>
            <h4><b>1)</b> What is a computer system?</h4>
        </section>
        <section>
            <p>A computer system is a combination of hardware and software that work together to perform different tasks.</p>
            <p>It accepts data, processes it according to instructions, stores it for future use and produces meaningful information as output. <b>[2]</b></p>
        </section>
    </section>

    <!-- 2 -->
    <section>
        <section>
            <h4><b>2)</b> State the four basic functions of a computer system.</h4>
        </section>
        <section>
            <p>The four basic functions are:</p>
            <ul>
                <li>Input</li>
                <li>Processing</li>
                <li>Storage</li>
                <li>Output</li>
            </ul>
            <p><b>[4]</b></p>
        </section>
    </section>

    <!-- 3 -->
    <section>
        <section>
            <h4><b>3)</b> Give an example showing the four basic functions of a computer system.</h4>
        </section>
        <section>
            <p>Example:</p>
            <p><b>Keyboard → CPU → RAM → Monitor</b></p>
            <p>The keyboard provides input, the CPU processes it, RAM provides storage during processing and the monitor provides output. <b>[4]</b></p>
        </section>
    </section>

    <!-- 4 -->
    <section>
        <section>
            <h4><b>4)</b> Define hardware and give two examples.</h4>
        </section>
        <section>
            <p>Hardware is the physical components of a computer system that can be seen and touched.</p>
            <p>Examples include the <b>CPU</b>, <b>RAM</b>, <b>keyboard</b> and <b>monitor</b>. <b>[3]</b></p>
        </section>
    </section>

    <!-- 5 -->
    <section>
        <section>
            <h4><b>5)</b> Define software and give two examples.</h4>
        </section>
        <section>
            <p>Software consists of programs that provide instructions for a computer system.</p>
            <p>Examples given in the notes include <b>Windows</b>, <b>Linux</b>, <b>Microsoft Word</b> and <b>Python</b>. <b>[3]</b></p>
        </section>
    </section>

    <!-- 6 -->
    <section>
        <section>
            <h4><b>6)</b> Explain the difference between hardware and software.</h4>
        </section>
        <section>
            <p><b>Hardware</b> is the physical part of a computer system, while <b>software</b> consists of programs/instructions that operate on the hardware. <b>[2]</b></p>
        </section>
    </section>

    <!-- 7 -->
    <section>
        <section>
            <h4><b>7)</b> Define computer architecture.</h4>
        </section>
        <section>
            <p>Computer architecture is the design and organisation of a computer system and how its components work together to execute programs efficiently. <b>[2]</b></p>
        </section>
    </section>

    <!-- 8 -->
    <section>
        <section>
            <h4><b>8)</b> What is the stored-program concept?</h4>
        </section>
        <section>
            <p>The stored-program concept is the idea that <b>program instructions and data are stored together in main memory (RAM)</b>.</p>
            <p>Programs are loaded into RAM before execution. <b>[2]</b></p>
        </section>
    </section>

    <!-- 9 -->
    <section>
        <section>
            <h4><b>9)</b> Explain how the stored-program concept allows the CPU to execute a program.</h4>
        </section>
        <section>
            <p>Program instructions and data are stored together in main memory. The CPU can therefore fetch instructions directly from memory and execute them. <b>[2]</b></p>
        </section>
    </section>

    <!-- 10 -->
    <section>
        <section>
            <h4><b>10)</b> State two benefits of the stored-program concept.</h4>
        </section>
        <section>
            <ul>
                <li>It provides flexibility.</li>
                <li>Programs can be easily updated and reused.</li>
                <li>It supports general-purpose computing.</li>
            </ul>
            <p>Any two. <b>[2]</b></p>
        </section>
    </section>

    <!-- 11 -->
    <section>
        <section>
            <h4><b>11)</b> State two drawbacks associated with the stored-program concept.</h4>
        </section>
        <section>
            <ul>
                <li>Malware can execute because programs are stored in memory and executed.</li>
                <li>Shared memory contributes to the Von Neumann bottleneck.</li>
            </ul>
            <p><b>[2]</b></p>
        </section>
    </section>

    <!-- 12 -->
    <section>
        <section>
            <h4><b>12)</b> Define Von Neumann architecture.</h4>
        </section>
        <section>
            <p>Von Neumann architecture is a computer design in which <b>instructions and data are stored in the same memory space</b> and use the same pathways. <b>[2]</b></p>
        </section>
    </section>

    <!-- 13 -->
    <section>
        <section>
            <h4><b>13)</b> What are fixed-program computers?</h4>
        </section>
        <section>
            <p>Fixed-program computers have a very specific function and cannot be reprogrammed.</p>
            <p><b>Example: a calculator.</b> <b>[2]</b></p>
        </section>
    </section>

    <!-- 14 -->
    <section>
        <section>
            <h4><b>14)</b> What are stored-program computers?</h4>
        </section>
        <section>
            <p>Stored-program computers can be programmed to carry out many different tasks because applications and instructions can be stored on them. <b>[2]</b></p>
        </section>
    </section>

    <!-- 15 -->
    <section>
        <section>
            <h4><b>15)</b> State the main components of Von Neumann architecture.</h4>
        </section>
        <section>
            <p>The main components are:</p>
            <ul>
                <li>CPU</li>
                <li>Memory</li>
                <li>Input</li>
                <li>Output</li>
                <li>Address Bus</li>
                <li>Data Bus</li>
                <li>Control Bus</li>
            </ul>
            <p><b>[7]</b></p>
        </section>
    </section>

    <!-- 16 -->
    <section>
        <section>
            <h4><b>16)</b> State three characteristics of Von Neumann architecture.</h4>
        </section>
        <section>
            <ul>
                <li>Data and instructions share the same memory.</li>
                <li>A shared bus is used.</li>
                <li>Instructions are executed sequentially.</li>
                <li>The fetch–decode–execute cycle is used.</li>
            </ul>
            <p>Any three. <b>[3]</b></p>
        </section>
    </section>

    <!-- 17 -->
    <section>
        <section>
            <h4><b>17)</b> State two advantages of Von Neumann architecture.</h4>
        </section>
        <section>
            <ul>
                <li>It is simple.</li>
                <li>It is flexible.</li>
                <li>It is cost-effective.</li>
                <li>It is widely used.</li>
            </ul>
            <p>Any two. <b>[2]</b></p>
        </section>
    </section>

    <!-- 18 -->
    <section>
        <section>
            <h4><b>18)</b> What is the CPU?</h4>
        </section>
        <section>
            <p>The Central Processing Unit (CPU) is the main part of a computer that controls how it works.</p>
            <p>It is made up of the <b>Control Unit (CU)</b>, <b>Arithmetic and Logic Unit (ALU)</b> and <b>registers</b>. <b>[3]</b></p>
        </section>
    </section>

    <!-- 19 -->
    <section>
        <section>
            <h4><b>19)</b> What is the function of the Control Unit (CU)?</h4>
        </section>
        <section>
            <p>The CU manages how the processor works by sending control signals.</p>
            <p>It controls how data moves inside the computer, controls input/output operations and fetches instructions from memory for execution. <b>[3]</b></p>
        </section>
    </section>

    <!-- 20 -->
    <section>
        <section>
            <h4><b>20)</b> What is the function of the ALU?</h4>
        </section>
        <section>
            <p>The Arithmetic and Logic Unit performs calculations and decision-making tasks.</p>
            <p>It performs arithmetic operations such as addition and subtraction, logical comparisons and bit-shifting operations. <b>[3]</b></p>
        </section>
    </section>

    <!-- 21 -->
    <section>
        <section>
            <h4><b>21)</b> What are registers?</h4>
        </section>
        <section>
            <p>Registers are very fast memory locations inside the CPU.</p>
            <p>They temporarily store information that the processor is currently working on, making execution faster and more efficient. <b>[2]</b></p>
        </section>
    </section>

    <!-- 22 -->
    <section>
        <section>
            <h4><b>22)</b> What is the function of the Program Counter (PC)?</h4>
        </section>
        <section>
            <p>The <b>PC</b> keeps track of the address of the <b>next instruction</b> to be executed. <b>[1]</b></p>
        </section>
    </section>

    <!-- 23 -->
    <section>
        <section>
            <h4><b>23)</b> What is the function of the Instruction Register (IR)?</h4>
        </section>
        <section>
            <p>The <b>IR</b> holds the current instruction being executed. <b>[1]</b></p>
            <p><small>The FDE notes also use the term <b>CIR (Current Instruction Register)</b> when describing the decode stage.</small></p>
        </section>
    </section>

    <!-- 24 -->
    <section>
        <section>
            <h4><b>24)</b> What is the function of the Memory Address Register (MAR)?</h4>
        </section>
        <section>
            <p>The <b>MAR</b> stores the address of the memory location being accessed. <b>[1]</b></p>
        </section>
    </section>

    <!-- 25 -->
    <section>
        <section>
            <h4><b>25)</b> What is the function of the Memory Data Register (MDR)?</h4>
        </section>
        <section>
            <p>The <b>MDR</b> temporarily holds data being transferred to or from memory. <b>[1]</b></p>
        </section>
    </section>

    <!-- 26 -->
    <section>
        <section>
            <h4><b>26)</b> What is the function of the accumulator (ACC)?</h4>
        </section>
        <section>
            <p>The <b>ACC</b> stores intermediate results of arithmetic and logic operations. <b>[1]</b></p>
        </section>
    </section>

    <!-- 27 -->
    <section>
        <section>
            <h4><b>27)</b> What are general-purpose registers used for?</h4>
        </section>
        <section>
            <p>General-purpose registers are used for temporary storage of data during processing. <b>[1]</b></p>
        </section>
    </section>

    <!-- 28 -->
    <section>
        <section>
            <h4><b>28)</b> What is a bus?</h4>
        </section>
        <section>
            <p>A bus is a communication system that transfers <b>data, addresses and control signals</b> between the CPU, memory and I/O devices. <b>[2]</b></p>
        </section>
    </section>

    <!-- 29 -->
    <section>
        <section>
            <h4><b>29)</b> What is the Data Bus?</h4>
        </section>
        <section>
            <p>The Data Bus is a group of wires/lines used to transfer data between the CPU, memory and I/O devices.</p>
            <p>It can carry numbers, instructions and other data. <b>[2]</b></p>
        </section>
    </section>

    <!-- 30 -->
    <section>
        <section>
            <h4><b>30)</b> What is the Address Bus?</h4>
        </section>
        <section>
            <p>The Address Bus carries the address/location of data in memory or I/O devices.</p>
            <p>It generally carries information from the CPU to memory or I/O. <b>[2]</b></p>
        </section>
    </section>

    <!-- 31 -->
    <section>
        <section>
            <h4><b>31)</b> What is the Control Bus?</h4>
        </section>
        <section>
            <p>The Control Bus carries control signals that coordinate operations between the CPU, memory and I/O devices.</p>
            <p>Examples include <b>Read, Write, Interrupt and Clock</b> signals. <b>[2]</b></p>
        </section>
    </section>

    <!-- 32 -->
    <section>
        <section>
            <h4><b>32)</b> What is an I/O interface?</h4>
        </section>
        <section>
            <p>An I/O interface connects the CPU and memory to input/output devices. <b>[1]</b></p>
        </section>
    </section>

    <!-- 33 -->
    <section>
        <section>
            <h4><b>33)</b> Explain the Von Neumann bottleneck.</h4>
        </section>
        <section>
            <p>The Von Neumann bottleneck is the limitation caused by the shared pathway between the CPU and memory.</p>
            <p>Because instructions and data use the same memory and bus, the CPU cannot fetch instructions and data simultaneously. The CPU may have to wait for memory, reducing overall performance. <b>[4]</b></p>
        </section>
    </section>

    <!-- 34 -->
    <section>
        <section>
            <h4><b>34)</b> State some applications of Von Neumann architecture.</h4>
        </section>
        <section>
            <ul>
                <li>Personal computers and laptops</li>
                <li>Smartphones and tablets</li>
                <li>Embedded systems</li>
                <li>Servers and cloud computing</li>
                <li>Gaming consoles</li>
            </ul>
            <p><b>[5]</b></p>
        </section>
    </section>

    <!-- 35 -->
    <section>
        <section>
            <h4><b>35)</b> Explain the fetch–decode–execute cycle.</h4>
        </section>
        <section>
            <p>The CPU repeatedly processes instructions using three stages:</p>
            <ol>
                <li><b>Fetch:</b> an instruction is obtained from memory.</li>
                <li><b>Decode:</b> the instruction is interpreted and required data is retrieved.</li>
                <li><b>Execute:</b> the CPU carries out the required action.</li>
            </ol>
            <p><b>[3]</b></p>
        </section>
    </section>

    <!-- 36 -->
    <section>
        <section>
            <h4><b>36)</b> Describe the fetch stage using the registers and buses.</h4>
        </section>
        <section>
            <ol>
                <li>The PC is loaded with 0.</li>
                <li>The value from the PC is copied to the MAR.</li>
                <li>The address from the MAR is sent across the Address Bus and a read signal is sent using the Control Bus.</li>
                <li>The data from that memory location is sent across the Data Bus to the MDR.</li>
                <li>The PC is incremented by 1.</li>
            </ol>
            <p><b>[5]</b></p>
        </section>
    </section>

    <!-- 37 -->
    <section>
        <section>
            <h4><b>37)</b> Describe the decode stage.</h4>
        </section>
        <section>
            <p>The data is sent from the <b>MDR</b> to the <b>CIR</b>, where it is split into the opcode and operand.</p>
            <p>The instruction is then sent to the <b>CU</b> to be decoded. <b>[2]</b></p>
        </section>
    </section>

    <!-- 38 -->
    <section>
        <section>
            <h4><b>38)</b> Explain what happens during the execute stage for an INP instruction.</h4>
        </section>
        <section>
            <p>If a value is being inputted using <b>INP</b>, the <b>ACC</b> stores the input value. <b>[1]</b></p>
        </section>
    </section>

    <!-- 39 -->
    <section>
        <section>
            <h4><b>39)</b> Explain what happens during the execute stage for an OUT instruction.</h4>
        </section>
        <section>
            <p>If a value is being output using <b>OUT</b>, the value currently stored in the <b>ACC</b> is output. <b>[1]</b></p>
        </section>
    </section>

    <!-- 40 -->
    <section>
        <section>
            <h4><b>40)</b> Explain what happens during execution of an LDA instruction.</h4>
        </section>
        <section>
            <p>The value is loaded from RAM from the address in the <b>MAR</b> and sent across the Data Bus to the <b>MDR</b>. <b>[2]</b></p>
        </section>
    </section>

    <!-- 41 -->
    <section>
        <section>
            <h4><b>41)</b> Explain what happens during execution of a STA instruction.</h4>
        </section>
        <section>
            <p>The value is taken from the <b>ACC</b> and sent to the <b>MDR</b>.</p>
            <p>It is then sent across the Data Bus to RAM at the address stored in the <b>MAR</b>. <b>[2]</b></p>
        </section>
    </section>

    <!-- 42 -->
    <section>
        <section>
            <h4><b>42)</b> Explain what happens when ADD or SUB is executed.</h4>
        </section>
        <section>
            <p>The values are passed to the <b>ALU</b>, the arithmetic operation is carried out and the result is stored in the <b>ACC</b>. <b>[2]</b></p>
        </section>
    </section>

    <!-- 43 -->
    <section>
        <section>
            <h4><b>43)</b> What happens when an LMC branch instruction such as BRA, BRZ or BRP is executed?</h4>
        </section>
        <section>
            <p>The comparison takes place in the <b>ALU</b>. <b>[1]</b></p>
        </section>
    </section>

    <!-- MCQ 1 -->
    <section>
        <section>
            <h4><b>44)</b> Which register stores the address of the next instruction?</h4>
            <p>A. MDR</p>
            <p>B. MAR</p>
            <p>C. IR</p>
            <p>D. PC</p>
        </section>
        <section>
            <h3>Answer: D. PC</h3>
            <p>The Program Counter stores the address of the next instruction to be executed.</p>
        </section>
    </section>

    <!-- MCQ 2 -->
    <section>
        <section>
            <h4><b>45)</b> Which register temporarily stores data transferred to or from memory?</h4>
            <p>A. PC</p>
            <p>B. MDR</p>
            <p>C. ALU</p>
            <p>D. CU</p>
        </section>
        <section>
            <h3>Answer: B. MDR</h3>
            <p>The Memory Data Register temporarily holds data being transferred to or from memory.</p>
        </section>
    </section>

    <!-- MCQ 3 -->
    <section>
        <section>
            <h4><b>46)</b> In the stored-program concept, instructions are stored in:</h4>
            <p>A. ROM only</p>
            <p>B. CPU cache only</p>
            <p>C. Main memory (RAM)</p>
            <p>D. Hard disk only</p>
        </section>
        <section>
            <h3>Answer: C. Main memory (RAM)</h3>
            <p>The notes define the stored-program concept as storing program instructions and data together in main memory (RAM).</p>
        </section>
    </section>

    <!-- MCQ 4 -->
    <section>
        <section>
            <h4><b>47)</b> Which bus carries memory addresses?</h4>
            <p>A. Data Bus</p>
            <p>B. Address Bus</p>
            <p>C. Control Bus</p>
            <p>D. System Bus</p>
        </section>
        <section>
            <h3>Answer: B. Address Bus</h3>
            <p>The Address Bus carries the address/location of data in memory or I/O devices.</p>
        </section>
    </section>

    <!-- MCQ 5 -->
    <section>
        <section>
            <h4><b>48)</b> Which bus carries control signals?</h4>
            <p>A. Address Bus</p>
            <p>B. Data Bus</p>
            <p>C. Control Bus</p>
            <p>D. Memory Bus</p>
        </section>
        <section>
            <h3>Answer: C. Control Bus</h3>
            <p>The Control Bus carries signals such as Read, Write, Interrupt and Clock.</p>
        </section>
    </section>

    <!-- MCQ 6 -->
    <section>
        <section>
            <h4><b>49)</b> Which architecture stores instructions and data in the same memory?</h4>
            <p>A. Harvard Architecture</p>
            <p>B. Von Neumann Architecture</p>
            <p>C. Parallel Architecture</p>
            <p>D. Distributed Architecture</p>
        </section>
        <section>
            <h3>Answer: B. Von Neumann Architecture</h3>
            <p>Von Neumann architecture stores instructions and data in the same main memory.</p>
        </section>
    </section>

    <!-- 50 -->
    <section>
        <section>
            <h4><b>50)</b> Give a structured explanation of the Von Neumann architecture, including its components and main limitation.</h4>
        </section>
        <section>
            <p>Von Neumann architecture stores program instructions and data in the same main memory.</p>
            <ul>
                <li>The <b>CPU</b> processes instructions and data.</li>
                <li><b>Memory</b> stores instructions and data.</li>
                <li><b>Input</b> devices provide data.</li>
                <li><b>Output</b> devices present results.</li>
                <li>The <b>Data Bus</b> transfers data.</li>
                <li>The <b>Address Bus</b> transfers addresses.</li>
                <li>The <b>Control Bus</b> transfers control signals.</li>
            </ul>
            <p>Its main limitation is the <b>Von Neumann bottleneck</b>, because instructions and data share the same memory pathway, limiting the rate at which the CPU can obtain them. <b>[6]</b></p>
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
        {
            src: '<?= $pptBase ?>/lib/js/classList.js',
            condition: function() {
                return !document.body.classList;
            }
        },
        {
            src: '<?= $pptBase ?>/plugin/markdown/marked.js',
            condition: function() {
                return !!document.querySelector('[data-markdown]');
            }
        },
        {
            src: '<?= $pptBase ?>/plugin/markdown/markdown.js',
            condition: function() {
                return !!document.querySelector('[data-markdown]');
            }
        },
        {
            src: '<?= $pptBase ?>/plugin/highlight/highlight.js',
            async: true,
            callback: function() {
                hljs.initHighlightingOnLoad();
            }
        },
        {
            src: '<?= $pptBase ?>/plugin/zoom-js/zoom.js',
            async: true,
            condition: function() {
                return !!document.body.classList;
            }
        }
    ]
});
</script>

</body>
</html>
