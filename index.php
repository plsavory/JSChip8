<?php

// This is a nasty/quick solution - would be nice to have a file selector in JavaScript or something like that later.
if (isset($_GET['rom'])) {
    $filename = "roms/" . $_GET['rom'];
} else {
    $filename = "roms/MAZE";
}

if (!file_exists($filename)) {
    die("Error: ROM file {$filename} does not exist.");
}

$data = file_get_contents($filename);

if (empty($data)) {
    die("Error: ROM file is empty");
}

$binaryToLoad = unpack("C*",$data);

$binaryString = implode(",",$binaryToLoad);
?>

<!DOCTYPE html>

<html>

<head>
	<title>SuperChip-8 Interpreter</title>
	<link rel="stylesheet" type="text/css" href="bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="style.css">
</head>

<body>

    <div id = "app" class="app">

        <div></div>

        <div class="emulator">

            <!--TODO: Put Title and ROM loading bar hre-->
            <div>
                <h1 style="text-align:center">SuperChip-8 JavaScript Interpreter</h1>
                <hr/>
            </div>

            <div class="contentHeader"></div>

            <div class="contentObject">

                    <h1 style="text-align: center">Display</h1>
                    <hr>

                    <div class="emulatorDisplay">
                        <div></div>
                        <canvas id="canvas" width="1280" height="640"></canvas>
                        <div></div>
                    </div>

                <!-- Emulator controls -->
                <hr>

                <div class="emulatorControls">
                    <div class="emulatorControlsBorder">
                        <div class="buttonGroup">

                            <div>
                            <button v-on:click="toggleDebuggerDisplay" class="retro-button button-green">Debugger</button>
                            </div>


                            <div>
                            <button v-on:click="resetCPU" class="retro-button button-green">Reset</button>
                            </div>

                            <div>
                            <button v-on:click="stepCPU" class="retro-button button-green">Step</button>
                            </div>

                            <div>
                            <div v-if="CPUState.state === 'running'">
                                <button v-if="" v-on:click="breakCPU" class="retro-button button-red">Break</button>
                            </div>

                            <div v-else>
                                <button v-if="" v-on:click="runCPU" class="retro-button button-green">Run</button>
                            </div>

                            </div>

                            <div>
                                Clock Speed: <input type="range" min="1" max="50" v-model="clockSpeed"/>
                            </div>
                        </div>

                     </div>
                </div>

            </div>
            <div class="contentFooter"></div>

            <br/>

            <div v-if="displayDebugger">
                <div class="contentHeader"></div>

                <div class="contentObject">
                    <div class="debugger">
                        <div>
                            <h2 style="text-align:center">Activity Log</h2>
                            <hr/>
                            <div style="height: 900px; overflow:scroll;">
                                    <table>
                                        <tr>
                                            <th>Opcode</th>
                                            <th>Mnemonic</th>
                                            <th>Arg</th>
                                            <th>Message</th>
                                        </tr>
                                        <tr v-for="item in CPULog">
                                            <th>{{item.opcode}}</th>
                                            <th>{{item.mnemonic}}</th>
                                            <th>{{item.arg}}</th>
                                            <th>{{item.message}}</th>
                                        </tr>
                                    </table>
                        </div>

                        </div>

                        <div>
                            <h2 style="text-align:center">Current CPU State</h2>
                            <hr/>

                            <div class="cpuStatus">
                                <div>
                                    <p style="text-align: center">Registers</p>
                                    <hr/>

                                    <p>Program Counter: {{displayHex(CPUState.pc)}}</p>

                                    <p>Index: {{displayHex(CPUState.i)}}</p>

                                    <p>Stack Pointer: {{displayHex(CPUState.stackPointer)}}</p>

                                    <p v-for="(item, index) in CPUState.gpRegisters">
                                        V{{displayRegisterID(index)}}: {{displayHex(item)}}
                                    </p>
                                </div>

                                <div>
                                    <p style="text-align: center">Memory</p>
                                    <hr/>

                                    <span v-if="displayMemory" v-html="getMemoryDisplay()"></span>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="contentFooter"></div>
            </div>

        </div>

    </div>

    <br/>

    <script src="vue.js"></script>

    <script>
        // FpsCtrl function was not written by me - needs redoing ASAP.
        // Source: https://stackoverflow.com/questions/19764018/controlling-fps-with-requestanimationframe
        function FpsCtrl(fps, callback) {

            this.isPlaying = false;

            // set frame-rate
            this.frameRate = function(newfps) {
                if (!arguments.length) return fps;
                fps = newfps;
                delay = 1000 / fps;
                frame = -1;
                time = null;
            };

            // enable starting/pausing of the object
            this.start = function() {
                if (!this.isPlaying) {
                    this.isPlaying = true;
                    tref = requestAnimationFrame(loop);
                }
            };

            this.pause = function() {
                if (this.isPlaying) {
                    cancelAnimationFrame(tref);
                    this.isPlaying = false;
                    time = null;
                    frame = -1;
                }
            };

            var delay = 1000 / fps,                               // calc. time per frame
                time = null,                                      // start time
                frame = -1,                                       // frame count
                tref;                                             // rAF time reference

            function loop(timestamp) {
                if (time === null) time = timestamp;              // init start time
                var seg = Math.floor((timestamp - time) / delay); // calc frame no.
                if (seg > frame) {                                // moved to next frame?
                    frame = seg;                                  // update
                    callback({                                    // callback function
                        time: timestamp,
                        frame: frame
                    })
                }
                tref = requestAnimationFrame(loop)
            }
        }


        new Vue({
            el: "#app",
            created: function () {
                window.addEventListener('keyup', this.keyUp);
                window.addEventListener('keydown', this.keyDown);
            },
            data: {
                CPUState: {
                    gpRegisters: [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
                    pc: 0x00,
                    i: 0x00,
                    flags: 0x00,
                    stackPointer: 0x00,
                    stack: [],
                    cpuMemory: [],
                    state: "paused",
                    delayTimer: 0,
                    soundTimer: 0,
                    waitingKeyRegister: 0x0
                },
                programData: [
                    <?=$binaryString;?>
                ],
                memoryDisplayHtml: "",
                CPULog: [],
                videoMemory: [], // 64x64 Pixels
                clockSpeed: 1,
                displayMemory: false,
                displayDebugger: false,
                keyboardMap: [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
                characterMap: [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
                largeCharacterMap: [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
                videoMode: 1
            },
            methods: {
                updateCanvas: function() {
                    var canvas = document.getElementById('canvas');
                    ctx = canvas.getContext('2d');

                    ctx.clearRect(0,0,canvas.width,canvas.height);
                    ctx.fillStyle = "black";

                    ctx.fillRect(0, 0, canvas.width, canvas.height);

                    // Update the canvas with the contents of video memory
                    ctx.fillStyle="green";

                    for (let iX = 0; iX<64*this.videoMode;iX++) {
                        for (let iY = 0; iY<32*this.videoMode; iY++) {
                            if (this.videoMemory[iX][iY]) {
                                ctx.fillRect(iX*(20/this.videoMode), iY*(20/this.videoMode),(20/this.videoMode), (20/this.videoMode));
                            }
                        }
                    }
                },
                displayHex: function(number) {
                    if (number < 0) {
                        number = 0xFFFFFFFF + number + 1;
                    }

                    if (number === null) {
                        return;
                    }

                    return "0x" + number.toString(16).toUpperCase();
                },
                displayRegisterID: function(number) {
                    if (number < 0) {
                        number = 0xFFFFFFFF + number + 1;
                    }

                    return number.toString(16).toUpperCase();
                },
                getMemoryDisplay: function() {

                    let displayString = "<table>";

                    for (iY = 0x200; iY<= 0x200+(this.programData.length); iY+=4) {

                        displayString = displayString + "<tr>";
                        for (iX = 0; iX<4; iX++) {
                            if (this.CPUState.cpuMemory[iX+iY] != null) {
                                displayLine = this.displayHex(this.CPUState.cpuMemory[iX + iY]);
                            } else {
                                displayLine = this.displayHex(0);
                            }

                            if (this.CPUState.pc === iX+iY) {
                                displayString = displayString + '<td style="background-color:lightgrey">' + displayLine + "</td>";
                            } else {
                                displayString = displayString + "<td>" + displayLine + "</td>";
                            }
                        }

                        displayString = displayString + "</tr>";

                    }

                    displayString = displayString + "</table>";

                    return displayString;
                },
                resetCPU: function () {
                    this.reset();
                },
                stepCPU: function () {
                    this.step();
                },
                loadROM: function () {
                    console.log("Loading ROM...");
                },
                toggleMemoryDisplay: function() {
                    this.displayMemory = !this.displayMemory;
                },
                toggleDebuggerDisplay: function() {
                    this.displayDebugger = !this.displayDebugger;
                },
                runCPU: function () {
                    this.CPUState.state = "running";

                    var that = this;

                    var fc = new FpsCtrl(60, function(e) {
                        if (that.CPUState.state === "running") {
                            for (var i = 0; i<=that.clockSpeed;i++) {
                                that.step();
                            }

                            // Reduce the delay timer if need be
                            if (that.CPUState.delayTimer > 0) {
                                that.CPUState.delayTimer--;
                            }
                        }
                    });

                    fc.start();
                },
                breakCPU: function() {
                  this.CPUState.state = "break";
                  clearInterval();
                },
                reset: function () {
                    // Reset all of the general purpose registers
                    for (let i = 0; i<16;i++) {
                        this.CPUState.gpRegisters[i] = 0x00;
                    }

                    // Reset program counter
                    this.CPUState.pc = 0x200;

                    // Reset memory address counter
                    this.CPUState.i = 0x00;

                    this.CPUState.flags = 0x00;

                    this.CPUState.stackPointer = 0xF;

                    this.CPUState.state = "waiting";

                    // Clear the stack
                    for (let i = 0; i<=0xF;i++) {
                        this.CPUState.stack[i] = 0x0;
                    }

                    // Clear the keyboard map
                    for (let i = 0; i<=0xF;i++) {
                        this.keyboardMap[i] = 0x0;
                    }

                    // Load the program into memory
                    for (let i = 0; i<=0xFFF; i++) {

                        if (i >= 0x200) {
                            this.CPUState.cpuMemory[i] = this.programData[i-0x200];
                        }

                    }

                    // Clear video memory
                    this.clearVideoMemory();

                    this.updateCanvas();

                    this.CPULog = [];

                    // Add the character sprites into memory
                    this.addCharactersToMemory();
                },
                clearVideoMemory() {
                    for (let iX = 0; iX<= 64*this.videoMode; iX++) {

                        this.videoMemory[iX] = new Array(64*this.videoMode);

                        for (let iY = 0; iY<=64; iY++) {
                            this.videoMemory[iX][iY] = 0x0;
                        }
                    }
                },
                writeVRegister: function (registerID, value) {
                    // Write to an 8-bit register

                    // Can't write to VF or higher
                    if (registerID > 0xE) {
                        return;
                    }

                    this.CPUState.gpRegisters[registerID] = value & 0xFF;
                },
                writeVFRegister: function(value) {
                    this.CPUState.gpRegisters[0xF] = value & 0xFF;
                },
                ni: function () {

                    // Fetch the next byte from memory and increment the program counter
                    let retVal = {
                        upper: this.CPUState.cpuMemory[this.CPUState.pc],
                        lower: this.CPUState.cpuMemory[this.CPUState.pc+1],
                        lowest: this.CPUState.cpuMemory[this.CPUState.pc+1] & 0x0F,
                        whole: this.CPUState.cpuMemory[this.CPUState.pc+1] + (this.CPUState.cpuMemory[this.CPUState.pc] << 8)
                    };

                    this.CPUState.pc+=2;

                    return retVal;

                },
                step: function() {

                    if (this.CPUState.state === "error") {
                        return;
                    }

                    let opcode = this.ni();

                    this.executeOpcode(opcode);
                },
                executeOpcode: function(opcode) {

                    // Executes a CPU opcode

                    // Match only the first 4 bits of the opcode in the first switch statement
                    switch((opcode.upper >> 4)) {
                        case 0x0:
                            switch(opcode.lower) {
                                case 0xE0:
                                    this.clsHandler(opcode);
                                    break;
                                case 0xEE:
                                    this.retHandler(opcode);
                                    break;
                                case 0xFE:
                                    this.setVideoMode(1);
                                    break;
                                case 0xFF:
                                    this.setVideoMode(2);
                                    break;
                                default:
                                    // Nasty hack due to time comstraint - fix later
                                    if ((((opcode.lower >> 4) & 0xFF) << 4) == 0xC0) {
                                        this.scrollDown(opcode);
                                    } else {
                                        this.CPUState.state = "error";

                                        this.logActivity({
                                            opcodeVal: opcode.whole,
                                            mnemonic: "UNK",
                                            arg: "",
                                            message: "Unknown Opcode"
                                        });
                                    }
                                    break;
                            }
                            break;
                        case 0x1:
                            this.jpHandler(opcode);
                            break;
                        case 0x2:
                            this.callHandler(opcode);
                            break;
                        case 0x3:
                            this.seHandler(opcode);
                            break;
                        case 0x4:
                            this.sneqHandler(opcode);
                            break;
                        case 0x5:
                            this.seqHandler(opcode);
                            break;
                        case 0x6:
                            this.ldxHandler(opcode);
                            break;
                        case 0x7:
                            this.setAdd(opcode);
                            break;
                        case 0x8:
                            switch (opcode.lowest) {
                                case 0x0:
                                    this.transferHandler(opcode);
                                    break;
                                case 0x1:
                                    this.orRegHandler(opcode);
                                    break;
                                case 0x2:
                                    this.vandHandler(opcode);
                                    break;
                                case 0x3:
                                    this.xorRegHandler(opcode);
                                    break;
                                case 0x4:
                                    this.addRegHandler(opcode);
                                    break;
                                case 0x5:
                                    this.subRegHandler(opcode);
                                    break;
                                case 0x6:
                                    this.shrHandler(opcode);
                                    break;
                                case 0x7:
                                    this.subnHandler(opcode);
                                    break;
                                case 0xE:
                                    this.shlHandler(opcode);
                                    break;
                                default:
                                    this.CPUState.state = "error";

                                    this.logActivity({
                                        opcodeVal: opcode.whole,
                                        mnemonic: "UNK",
                                        arg: "",
                                        message: "Unknown Opcode"
                                    });
                                    break;
                            }
                            break;
                        case 0x9:
                            this.sneHandler(opcode);
                            break;
                        case 0xA:
                            this.ldiHandler(opcode);
                            break;
                        case 0xC:
                            this.rndHandler(opcode);
                            break;
                        case 0xD:
                            this.drawSprite(opcode);
                            break;
                        case 0xE:
                            switch(opcode.lower) {
                                case 0x9E:
                                    this.skipIfKeyPressed(opcode);
                                    break;
                                case 0xA1:
                                    this.skipIfKeyNotPressed(opcode);
                                    break;
                                default:
                                    this.CPUState.state = "error";

                                    this.logActivity({
                                        opcodeVal: opcode.whole,
                                        mnemonic: "UNK",
                                        arg: "",
                                        message: "Unknown Opcode"
                                    });
                                    break;
                            }
                            break;
                        case 0xF:
                            switch(opcode.lower) {
                                case 0x07:
                                    this.getDelayTimer(opcode);
                                    break;
                                case 0x0A:
                                    this.CPUState.state = 'waitForInput';
                                    this.CPUState.waitingKeyRegister = this.getRegisterId(opcode.whole);
                                    break;
                                case 0x15:
                                    this.setDelayTimer(opcode);
                                    break;
                                case 0x18:
                                    this.setSoundTimer(opcode);
                                    break;
                                case 0x1E:
                                    this.addIHandler(opcode);
                                    break;
                                case 0x29:
                                    this.setItoCharacterLocation(opcode);
                                    break;
                                case 0x30:
                                    this.setItoLargeCharacterLocation(opcode);
                                    break;
                                case 0x33:
                                    this.storeBCD(opcode);
                                    break;
                                case 0x55:
                                    this.storeRegistersInMemory(opcode);
                                    break;
                                case 0x65:
                                    this.fillRegisters(opcode);
                                    break;
                                default:
                                    this.CPUState.state = "error";

                                    this.logActivity({
                                        opcodeVal: opcode.whole,
                                        mnemonic: "UNK",
                                        arg: "",
                                        message: "Unknown Opcode"
                                    });
                                    break;
                            }
                            break;
                        default:
                            this.CPUState.state = "error";

                            this.logActivity({
                                opcodeVal: opcode.whole,
                                mnemonic: "UNK",
                                arg: "",
                                message: "Unknown Opcode"
                            });
                            break;
                    }

                },
                logActivity(logObject) {

                    if (this.CPUState.state === "running" || this.CPUState.state === "waitForInput") {
                        return;
                    }

                    outputObj = {
                        opcode: "",
                        mnemonic: "",
                        arg: "",
                        message: "",
                    };

                    if (logObject.opcodeVal != null) {
                        outputObj.opcode = this.displayHex(logObject.opcodeVal);
                    }

                    if (logObject.mnemonic != null) {
                        outputObj.mnemonic = logObject.mnemonic;
                    }

                    if (logObject.arg != null || logObject.arg !== "") {
                        outputObj.arg = this.displayHex(logObject.arg);
                    }

                    if (logObject.message != null) {
                        outputObj.message = logObject.message;
                    }

                    this.CPULog.push(outputObj);
                },
                ldiHandler: function(opcode) {
                    // Set I = nnn.

                    // Discard the lower 4 bits of the opcode
                    this.CPUState.i = this.get12BitArg(opcode.whole);

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "LDI",
                        arg: this.CPUState.i,
                        message: ""
                    });

                },
                rndHandler: function(opcode) {
                    // Set Vx = random byte AND kk

                    let randomValue = (Math.floor(Math.random() * 255) & this.get8BitArg(opcode.whole));

                    this.writeVRegister(this.getRegisterId(opcode.whole),randomValue);

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "RND",
                        arg: this.getRegisterId(opcode.whole),
                        message: ""
                    });
                },
                seHandler: function(opcode) {
                    // Skip next instruction if Vx = kk.
                    if (this.CPUState.gpRegisters[this.getRegisterId(opcode.whole)] === this.get8BitArg(opcode.whole)) {
                        this.CPUState.pc += 2; // Skip an opcode.
                    }

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "SE",
                        arg: this.getRegisterId(opcode.whole),
                        message: this.displayHex(this.get8BitArg(opcode.whole))
                    });
                },
                drawSprite: function(opcode) {
                    // Display n-byte sprite starting at memory location I at (Vx, Vy), set VF = collision.

                    let x = this.CPUState.gpRegisters[this.getRegisterId(opcode.whole)];
                    let y = this.CPUState.gpRegisters[this.get2ndRegisterId(opcode.whole)];
                    let height = this.get4BitArg(opcode.whole);

                    // if Argument is 0 and we're in SuperChip mode, we need to draw a 16x16 sprite.
                    if (height === 0 && this.videoMode === 2) {

                        height = 16;

                        for (let iY = 0; iY < height; iY++) {

                            let spriteBitmapLineL = this.CPUState.cpuMemory[this.CPUState.i + (iY*2)];
                            let spriteBitmapLineR = this.CPUState.cpuMemory[this.CPUState.i + (iY*2)+1];

                            let setVF = false;

                            let videoWidth = 64 * this.videoMode;
                            let videoHeight = 64 * this.videoMode;

                            for (let iX = 0; iX < 8; iX++) {

                                let mask = 1 << (7 - iX);

                                // Left side
                                if ((spriteBitmapLineL & mask) !== 0) {

                                        if (this.videoMemory[(x + iX) % videoWidth][(y + iY) % videoHeight]) {
                                            setVF = true;
                                        }

                                        this.videoMemory[(x + iX) % videoWidth][(y + iY) % videoHeight] ^= 1;
                                    }
                                else
                                    {
                                        this.videoMemory[(x + iX) % videoWidth][(y + iY) % videoHeight] ^= 0;
                                    }

                                // Right side
                                if ((spriteBitmapLineR & mask) !== 0) {

                                    if (this.videoMemory[(x + iX + 8) % videoWidth][(y + iY) % videoHeight]) {
                                        setVF = true;
                                    }

                                    this.videoMemory[(x + iX + 8) % videoWidth][(y + iY) % videoHeight] ^= 1;
                                }
                                else
                                {
                                    this.videoMemory[(x + iX + 8) % videoWidth][(y + iY) % videoHeight] ^= 0;
                                }

                                if (setVF) {
                                    this.writeVFRegister(true);
                                } else {
                                    this.writeVFRegister(false);
                                }
                            }
                        }

                    } else {

                        for (let iY = 0; iY < height; iY++) {
                            // Draw a line of the sprite
                            let spriteBitmapLine = this.CPUState.cpuMemory[this.CPUState.i + iY];

                            let setVF = false;

                            let videoWidth = 64 * this.videoMode;
                            let videoHeight = 64 * this.videoMode;

                            for (let iX = 0; iX < 8; iX++) {
                                let mask = 1 << (7 - iX);

                                if ((spriteBitmapLine & mask) !== 0) {

                                    if (this.videoMemory[(x + iX) % videoWidth][(y + iY) % videoHeight]) {
                                        setVF = true;
                                    }

                                    this.videoMemory[(x + iX) % videoWidth][(y + iY) % videoHeight] ^= 1;
                                } else {
                                    this.videoMemory[(x + iX) % videoWidth][(y + iY) % videoHeight] ^= 0;
                                }
                            }

                            if (setVF) {
                                this.writeVFRegister(true);
                            } else {
                                this.writeVFRegister(false);
                            }
                        }
                    }

                    // Update the canvas
                    this.updateCanvas();

                    // Log activity
                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "DRW",
                        arg: this.get12BitArg(opcode.whole),
                        message: ""
                    });

                },
                setAdd: function(opcode) {
                    // Set Vx = Vx + kk.
                    let addValue = this.CPUState.gpRegisters[this.getRegisterId(opcode.whole)] + this.get8BitArg(opcode.whole);
                    this.writeVRegister(this.getRegisterId(opcode.whole),addValue);

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "ADD(R)",
                        arg: this.getRegisterId(opcode.whole),
                        message: this.displayHex(this.get8BitArg(opcode.whole))
                    });

                },
                jpHandler: function(opcode) {
                    // Jump to location nnn.
                    this.CPUState.pc = this.get12BitArg(opcode.whole);

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "JP",
                        arg: this.get12BitArg(opcode.whole),
                        message: ""
                    });

                },
                ldxHandler: function(opcode) {
                    this.writeVRegister(this.getRegisterId(opcode.whole),this.get8BitArg(opcode.whole));

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "LDX",
                        arg: this.getRegisterId(opcode.whole),
                        message: this.get8BitArg(opcode.whole)
                    });
                },
                clsHandler: function(opcode) {
                    // Clear video memory
                    for (let iX = 0; iX<= 64*this.videoMode; iX++) {

                        this.videoMemory[iX] = new Array(64);

                        for (let iY = 0; iY<=64*this.videoMode; iY++) {
                            this.videoMemory[iX][iY] = 0x0;
                        }
                    }

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "CLS",
                        arg: "",
                        message: ""
                    });
                },
                setDelayTimer: function(opcode) {
                    // Set delay timer = Vx.
                    this.CPUState.delayTimer = this.CPUState.gpRegisters[this.getRegisterId(opcode.whole)];

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "LDDT",
                        arg: "",
                        message: ""
                    });
                },
                setSoundTimer: function(opcode) {
                  this.CPUState.soundTimer = this.CPUState.gpRegisters[this.getRegisterId(opcode.whole)];

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "LDST",
                        arg: "",
                        message: ""
                    });
                },
                getDelayTimer: function(opcode) {
                    // Store the contents of delayTimer into VX
                    let register = this.getRegisterId(opcode.whole);

                    this.writeVRegister(register,this.CPUState.delayTimer);
                },
                fillRegisters: function(opcode) {
                    // Read registers V0 through Vx from memory starting at location I.
                    let registerCount = this.getRegisterId(opcode.whole);
                    for (let i = 0; i <= registerCount; i++) {
                        this.writeVRegister(i,this.CPUState.cpuMemory[this.CPUState.i + i]);
                    }

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "FRG",
                        arg: this.displayHex(registerCount),
                        message: ""
                    });
                },
                shrHandler: function(opcode) {
                  let register = this.getRegisterId(opcode.whole);

                  if (this.bitTest(this.CPUState.gpRegisters[register],0)) {
                      this.writeVFRegister(1);
                  } else {
                      this.writeVFRegister(0);
                  }

                  this.writeVRegister(register,(this.CPUState.gpRegisters[register] / 2));

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "SHR",
                        arg: this.displayHex(register),
                        message: ""
                    });
                },
                transferHandler: function(opcode) {
                    // Set Vx = Vy.
                    this.writeVRegister(this.getRegisterId(opcode.whole),this.CPUState.gpRegisters[this.get2ndRegisterId(opcode.whole)]);

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "TRV",
                        arg: this.displayHex(this.getRegisterId(opcode.whole)),
                        message: ""
                    });
                },
                orRegHandler: function(opcode) {
                  let register1 = this.getRegisterId(opcode.whole);
                  let register2 = this.get2ndRegisterId(opcode.whole);

                  this.CPUState.gpRegisters[register1] |= this.CPUState.gpRegisters[register2];

                },
                xorRegHandler: function(opcode) {
                    let register1 = this.getRegisterId(opcode.whole);
                    let register2 = this.get2ndRegisterId(opcode.whole);

                    this.CPUState.gpRegisters[register1] ^= this.CPUState.gpRegisters[register2];
                },
                shlHandler: function(opcode) {
                    // Set Vx = Vx SHL 1.
                    let register = this.getRegisterId(opcode.whole);

                    if (this.bitTest(this.CPUState.gpRegisters[register],7)) {
                        this.writeVFRegister(true);
                    } else {
                        this.writeVFRegister(false);
                    }

                    this.writeVRegister(register,(this.CPUState.gpRegisters[register] * 2));

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "SHL",
                        arg: this.displayHex(this.getRegisterId(opcode.whole)),
                        message: ""
                    });
                },
                sneHandler: function(opcode) {

                    let register1 = this.getRegisterId(opcode.whole);
                    let register2 = this.get2ndRegisterId(opcode.whole);

                    if (this.CPUState.gpRegisters[register1] !== this.CPUState.gpRegisters[register2]) {
                        this.CPUState.pc += 2;
                    }

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "SNE",
                        arg: this.displayHex(this.getRegisterId(opcode.whole)),
                        message: this.displayHex(this.get2ndRegisterId(opcode.whole))
                    });

                },
                callHandler: function(opcode) {

                    // Call subroutine at nnn.
                    let subroutineAddess = this.get12BitArg(opcode.whole);

                    this.pushStack(this.CPUState.pc);
                    this.CPUState.pc = subroutineAddess;

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "CALL",
                        arg: subroutineAddess,
                        message: ""
                    });
                },
                sneqHandler: function(opcode) {
                    // Skip next instruction if Vx != kk.
                    let comparison = this.get8BitArg(opcode.whole);
                    let register = this.getRegisterId(opcode.whole);

                    if (this.CPUState.gpRegisters[register] !== comparison) {
                        this.CPUState.pc+=2;
                    }

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "SNEQ",
                        arg: register,
                        message: comparison
                    });
                },
                retHandler: function(opcode) {
                    // Return from a subroutine.
                    this.CPUState.pc = this.popStack();

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "RET",
                        arg: "",
                        message: ""
                    });
                },
                addIHandler: function(opcode) {
                    // Set I = I + Vx.
                    let register = this.getRegisterId(opcode.whole);
                    this.CPUState.i += this.CPUState.gpRegisters[register];

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "ADDI",
                        arg: register,
                        message: ""
                    });
                },
                vandHandler: function(opcode) {
                  register1 = this.getRegisterId(opcode.whole);
                  register2 = this.get2ndRegisterId(opcode.whole);

                  this.writeVRegister(register1, (this.CPUState.gpRegisters[register1] & this.CPUState.gpRegisters[register2]));

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "VAND",
                        arg: "",
                        message: ""
                    });

                },
                addRegHandler: function(opcode) {
                    // Set Vx = Vx + Vy, set VF = carry.
                    let register1 = this.getRegisterId(opcode.whole);
                    let register2 = this.get2ndRegisterId(opcode.whole);

                    let result = this.CPUState.gpRegisters[register1] + this.CPUState.gpRegisters[register2];

                    if (result > 0xFF) {
                        this.writeVFRegister(1);
                    } else {
                        this.writeVFRegister(0);
                    }

                    this.writeVRegister(register1,result);

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "ADDREG",
                        arg: this.displayRegisterID(register1),
                        message: this.displayRegisterID(register2)
                    });
                },
                subRegHandler: function(opcode) {
                    let register1 = this.getRegisterId(opcode.whole);
                    let register2 = this.get2ndRegisterId(opcode.whole);

                    if (this.CPUState.gpRegisters[register1] > this.CPUState.gpRegisters[register2]) {
                        this.writeVFRegister(true);
                    } else {
                        this.writeVFRegister(false);
                    }

                    let result = this.CPUState.gpRegisters[register1] - this.CPUState.gpRegisters[register2];

                    this.writeVRegister(register1,result);
                },
                subnHandler: function(opcode) {
                    let register1 = this.getRegisterId(opcode.whole);
                    let register2 = this.get2ndRegisterId(opcode.whole);

                    if (this.CPUState.gpRegisters[register2] > this.CPUState.gpRegisters[register1]) {
                        this.writeVFRegister(true);
                    } else {
                        this.writeVFRegister(false);
                    }

                    let result = this.CPUState.gpRegisters[register1] - this.CPUState.gpRegisters[register2];

                    this.writeVRegister(register1,result);
                },
                skipIfKeyPressed: function(opcode) {
                    let key = this.CPUState.gpRegisters[this.getRegisterId(opcode.whole)];

                    if (this.keyboardMap[key]) {
                        this.CPUState.pc += 2;
                    }

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "SKP",
                        arg: "",
                        message: ""
                    });
                },
                skipIfKeyNotPressed: function(opcode) {
                    let key = this.CPUState.gpRegisters[this.getRegisterId(opcode.whole)];

                    if (!this.keyboardMap[key]) {
                        this.CPUState.pc += 2;
                    }

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "SKNP",
                        arg: "",
                        message: ""
                    });
                },
                get12BitArg: function(value) {

                    // Discards the highest 4 bits of an opcode
                    return ((value << 4)&0xFFFF) >> 4;

                },
                get8BitArg: function(value) {

                    // Discards the lowest 8 bits of an opcode
                    return ((value << 8)&0xFFFF) >> 8;

                },
                get4BitArg: function(value) {

                    // Discards the highest 8 bits of an opcode
                    return ((value << 12)&0xFFFF) >> 12;

                },
                getRegisterId: function(value) {

                    // Discards everything except bits 5,6,7,8
                    return ((value << 4)&0xFFFF) >> 12;
                },
                get2ndRegisterId: function(value) {

                    // Discards everything except bits 9,10,11,12
                    return ((value << 8)&0xFFFF) >> 12;
                },
                bitTest: function(num, bit) {
                    return ((num>>bit) % 2 != 0);
                },
                pushStack: function(value) {
                    this.CPUState.stack[this.CPUState.stackPointer] = value & 0xFFFF; // Stack is an array of 16-bit values
                    this.CPUState.stackPointer--;
                },
                popStack: function() {
                    this.CPUState.stackPointer++;
                    let retVal = this.CPUState.stack[this.CPUState.stackPointer];
                    this.CPUState.stack[this.CPUState.stackPointer] = 0x0;
                    return retVal;
                },
                setItoCharacterLocation: function(opcode) {
                    let register1 = this.getRegisterId(opcode.whole);
                    character = this.CPUState.gpRegisters[register1];
                    this.CPUState.i = this.characterMap[character];
                },
                setItoLargeCharacterLocation: function(opcode) {
                    let register1 = this.getRegisterId(opcode.whole);
                    character = this.CPUState.gpRegisters[register1];
                    this.CPUState.i = this.largeCharacterMap[character];
                },
                storeRegistersInMemory: function(opcode) {
                    // Fx55 - LD [I], Vx

                    endRegister = this.getRegisterId(opcode.whole);

                    for (let i = 0; i <=endRegister; i++) {
                        this.CPUState.cpuMemory[this.CPUState.i+i] = this.CPUState.gpRegisters[i];
                    }
                },
                storeBCD(opcode) {
                    let value = this.CPUState.gpRegisters[this.getRegisterId(opcode.whole)];

                    this.CPUState.cpuMemory[this.CPUState.i] = ((value/100)&0xFF);
                    this.CPUState.cpuMemory[this.CPUState.i+1] = ((value/10)%10)&0xFF;
                    this.CPUState.cpuMemory[this.CPUState.i+2] = (value%10)&0xFF;

                },
                seqHandler(opcode) {

                    register1 = this.getRegisterId(opcode.whole);
                    register2 = this.get2ndRegisterId(opcode.whole);

                    if (this.CPUState.gpRegisters[register1] === this.CPUState.gpRegisters[register2]) {
                        this.CPUState.pc+=2;
                    }

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "SEQ",
                        arg: this.displayHex(this.getRegisterId(opcode.whole)),
                        message: this.displayHex(this.get2ndRegisterId(opcode.whole))
                    });
                },
                scrollDown: function(opcode) {
                    // Scroll the screen downwards by the amount of the right-side of the opcode
                    let amount = opcode.whole&0x0F;

                    // Shift every pixel on the screen downwards by this amount
                    for (let iY = 64; iY>=0; iY--) {
                        for (let iX = 0; iX<=128; iX++) {
                            this.videoMemory[iX][iY+amount] ^= this.videoMemory[iX][iY];
                            this.videoMemory[iX][iY] = 0x0;
                        }
                    }
                },
                keyUp: function (key) {
                    this.setKey(key.keyCode, false);
                },
                keyDown: function(key) {

                    if (this.CPUState.state === "waitForInput") {
                        this.CPUState.gpRegisters[this.CPUState.waitingKeyRegister] = this.getKeyId(key.keyCode);
                        this.CPUState.state = "running";
                    } else {
                        this.setKey(key.keyCode, true);
                    }

                },
                setKey: function(keyCode,val) {
                    keyId = this.getKeyId(keyCode);

                    if (keyId) {
                        this.keyboardMap[keyId] = val;
                    }
                },
                getKeyId: function(keyCode) {

                    switch(keyCode) {
                        case 12: // X
                             return 0x1;
                        case 187: // =
                            return 0x2;
                        case 111: // /
                            return 0x3;
                        case 81: // Q
                            return 0xC;
                        case 55: // 7
                            return 0x4;
                        case 56: // 8
                            return 0x5;
                        case 57: // 9
                            return 0x6;
                        case 87: // W
                            return 0xD;
                        case 52: // 4
                            return 0x7;
                        case 53: // 5
                            return 0x8;
                        case 54: // 6
                            return 0x9;
                        case 69: // E
                            return 0xE;
                        case 49: // 1
                            return 0xA;
                        case 50: // 2
                            return 0x0;
                        case 51: // 3
                            return 0xB;
                        case 82:
                            return 0xF;
                        default:
                            return null;
                    }

                },
                addCharactersToMemory: function() {

                    // 0
                    this.CPUState.cpuMemory[0x0] = 0xF0;
                    this.CPUState.cpuMemory[0x1] = 0x90;
                    this.CPUState.cpuMemory[0x2] = 0x90;
                    this.CPUState.cpuMemory[0x3] = 0x90;
                    this.CPUState.cpuMemory[0x4] = 0xF0;

                    // 1
                    this.CPUState.cpuMemory[0x5] = 0x20;
                    this.CPUState.cpuMemory[0x6] = 0x60;
                    this.CPUState.cpuMemory[0x7] = 0x20;
                    this.CPUState.cpuMemory[0x8] = 0x20;
                    this.CPUState.cpuMemory[0x9] = 0x70;

                    // 2
                    this.CPUState.cpuMemory[0xA] = 0xF0;
                    this.CPUState.cpuMemory[0xB] = 0x10;
                    this.CPUState.cpuMemory[0xC] = 0xF0;
                    this.CPUState.cpuMemory[0xD] = 0x80;
                    this.CPUState.cpuMemory[0xE] = 0xF0;

                    // 3
                    this.CPUState.cpuMemory[0xF] = 0xF0;
                    this.CPUState.cpuMemory[0x10] = 0x10;
                    this.CPUState.cpuMemory[0x11] = 0xF0;
                    this.CPUState.cpuMemory[0x12] = 0x10;
                    this.CPUState.cpuMemory[0x13] = 0xF0;

                    // 4
                    this.CPUState.cpuMemory[0x14] = 0x90;
                    this.CPUState.cpuMemory[0x15] = 0x90;
                    this.CPUState.cpuMemory[0x16] = 0xF0;
                    this.CPUState.cpuMemory[0x17] = 0x10;
                    this.CPUState.cpuMemory[0x18] = 0x10;

                    // 5
                    this.CPUState.cpuMemory[0x19] = 0xF0;
                    this.CPUState.cpuMemory[0x1A] = 0x80;
                    this.CPUState.cpuMemory[0x1B] = 0xF0;
                    this.CPUState.cpuMemory[0x1C] = 0x10;
                    this.CPUState.cpuMemory[0x1D] = 0xF0;

                    // 6
                    this.CPUState.cpuMemory[0x1E] = 0xF0;
                    this.CPUState.cpuMemory[0x1F] = 0x80;
                    this.CPUState.cpuMemory[0x20] = 0xF0;
                    this.CPUState.cpuMemory[0x21] = 0x90;
                    this.CPUState.cpuMemory[0x22] = 0xF0;

                    // 7
                    this.CPUState.cpuMemory[0x23] = 0xF0;
                    this.CPUState.cpuMemory[0x24] = 0x10;
                    this.CPUState.cpuMemory[0x25] = 0x20;
                    this.CPUState.cpuMemory[0x26] = 0x40;
                    this.CPUState.cpuMemory[0x27] = 0x40;

                    // 8
                    this.CPUState.cpuMemory[0x28] = 0xF0;
                    this.CPUState.cpuMemory[0x29] = 0x90;
                    this.CPUState.cpuMemory[0x2A] = 0xF0;
                    this.CPUState.cpuMemory[0x2B] = 0x90;
                    this.CPUState.cpuMemory[0x2C] = 0xF0;

                    // 9
                    this.CPUState.cpuMemory[0x2D] = 0xF0;
                    this.CPUState.cpuMemory[0x2E] = 0x90;
                    this.CPUState.cpuMemory[0x2F] = 0xF0;
                    this.CPUState.cpuMemory[0x30] = 0x10;
                    this.CPUState.cpuMemory[0x31] = 0xF0;

                    // A
                    this.CPUState.cpuMemory[0x32] = 0xF0;
                    this.CPUState.cpuMemory[0x33] = 0x90;
                    this.CPUState.cpuMemory[0x34] = 0xF0;
                    this.CPUState.cpuMemory[0x35] = 0x90;
                    this.CPUState.cpuMemory[0x36] = 0x90;

                    // B
                    this.CPUState.cpuMemory[0x37] = 0xE0;
                    this.CPUState.cpuMemory[0x38] = 0x90;
                    this.CPUState.cpuMemory[0x39] = 0xE0;
                    this.CPUState.cpuMemory[0x3A] = 0x90;
                    this.CPUState.cpuMemory[0x3B] = 0xE0;

                    // C
                    this.CPUState.cpuMemory[0x3C] = 0xF0;
                    this.CPUState.cpuMemory[0x3D] = 0x80;
                    this.CPUState.cpuMemory[0x3E] = 0x80;
                    this.CPUState.cpuMemory[0x3F] = 0x80;
                    this.CPUState.cpuMemory[0x40] = 0xF0;

                    // D
                    this.CPUState.cpuMemory[0x41] = 0xE0;
                    this.CPUState.cpuMemory[0x42] = 0x90;
                    this.CPUState.cpuMemory[0x43] = 0x90;
                    this.CPUState.cpuMemory[0x44] = 0x90;
                    this.CPUState.cpuMemory[0x45] = 0xE0;

                    // E
                    this.CPUState.cpuMemory[0x46] = 0xF0;
                    this.CPUState.cpuMemory[0x47] = 0x80;
                    this.CPUState.cpuMemory[0x48] = 0xF0;
                    this.CPUState.cpuMemory[0x49] = 0x80;
                    this.CPUState.cpuMemory[0x4A] = 0xF0;

                    // F
                    this.CPUState.cpuMemory[0x4B] = 0xF0;
                    this.CPUState.cpuMemory[0x4C] = 0x80;
                    this.CPUState.cpuMemory[0x4D] = 0xF0;
                    this.CPUState.cpuMemory[0x4E] = 0x80;
                    this.CPUState.cpuMemory[0x4F] = 0x80;

                    // Set the character map to point to the correct memory locations
                    this.characterMap = [0x0,0x5,0xA,0xF,0x14,0x19,0x1E,0x23,0x28,0x2D,0x32,0x37,0x3C,0x41,0x46,0x4B];

                    // SuperChip Characters

                    // 0
                    this.CPUState.cpuMemory[0x50] = 0xF0;
                    this.CPUState.cpuMemory[0x51] = 0xF0;
                    this.CPUState.cpuMemory[0x52] = 0x90;
                    this.CPUState.cpuMemory[0x53] = 0x90;
                    this.CPUState.cpuMemory[0x54] = 0x90;
                    this.CPUState.cpuMemory[0x55] = 0x90;
                    this.CPUState.cpuMemory[0x56] = 0x90;
                    this.CPUState.cpuMemory[0x57] = 0x90;
                    this.CPUState.cpuMemory[0x58] = 0xF0;
                    this.CPUState.cpuMemory[0x59] = 0xF0;

                    // 1
                    this.CPUState.cpuMemory[0x5A] = 0x20;
                    this.CPUState.cpuMemory[0x5B] = 0x20;
                    this.CPUState.cpuMemory[0x5C] = 0x60;
                    this.CPUState.cpuMemory[0x5D] = 0x60;
                    this.CPUState.cpuMemory[0x5E] = 0x20;
                    this.CPUState.cpuMemory[0x5F] = 0x20;
                    this.CPUState.cpuMemory[0x60] = 0x20;
                    this.CPUState.cpuMemory[0x61] = 0x20;
                    this.CPUState.cpuMemory[0x62] = 0x70;
                    this.CPUState.cpuMemory[0x63] = 0x70;

                    // 2
                    this.CPUState.cpuMemory[0x64] = 0xF0;
                    this.CPUState.cpuMemory[0x65] = 0xF0;
                    this.CPUState.cpuMemory[0x66] = 0x10;
                    this.CPUState.cpuMemory[0x67] = 0x10;
                    this.CPUState.cpuMemory[0x68] = 0xF0;
                    this.CPUState.cpuMemory[0x69] = 0xF0;
                    this.CPUState.cpuMemory[0x6A] = 0x80;
                    this.CPUState.cpuMemory[0x6B] = 0x80;
                    this.CPUState.cpuMemory[0x6C] = 0xF0;
                    this.CPUState.cpuMemory[0x6D] = 0xF0;

                    // 3
                    this.CPUState.cpuMemory[0x6E] = 0xF0;
                    this.CPUState.cpuMemory[0x6F] = 0xF0;
                    this.CPUState.cpuMemory[0x70] = 0x10;
                    this.CPUState.cpuMemory[0x71] = 0x10;
                    this.CPUState.cpuMemory[0x72] = 0xF0;
                    this.CPUState.cpuMemory[0x73] = 0xF0;
                    this.CPUState.cpuMemory[0x74] = 0x10;
                    this.CPUState.cpuMemory[0x75] = 0x10;
                    this.CPUState.cpuMemory[0x76] = 0xF0;
                    this.CPUState.cpuMemory[0x77] = 0xF0;

                    // 4
                    this.CPUState.cpuMemory[0x78] = 0x90;
                    this.CPUState.cpuMemory[0x79] = 0x90;
                    this.CPUState.cpuMemory[0x7A] = 0x90;
                    this.CPUState.cpuMemory[0x7B] = 0x90;
                    this.CPUState.cpuMemory[0x7C] = 0xF0;
                    this.CPUState.cpuMemory[0x7D] = 0xF0;
                    this.CPUState.cpuMemory[0x7E] = 0x10;
                    this.CPUState.cpuMemory[0x7F] = 0x10;
                    this.CPUState.cpuMemory[0x80] = 0x10;
                    this.CPUState.cpuMemory[0x81] = 0x10;

                    // 5
                    this.CPUState.cpuMemory[0x82] = 0xF0;
                    this.CPUState.cpuMemory[0x83] = 0xF0;
                    this.CPUState.cpuMemory[0x84] = 0x80;
                    this.CPUState.cpuMemory[0x85] = 0x80;
                    this.CPUState.cpuMemory[0x86] = 0xF0;
                    this.CPUState.cpuMemory[0x87] = 0xF0;
                    this.CPUState.cpuMemory[0x88] = 0x10;
                    this.CPUState.cpuMemory[0x89] = 0x10;
                    this.CPUState.cpuMemory[0x8A] = 0xF0;
                    this.CPUState.cpuMemory[0x8B] = 0xF0;

                    // 6
                    this.CPUState.cpuMemory[0x8C] = 0xF0;
                    this.CPUState.cpuMemory[0x8D] = 0xF0;
                    this.CPUState.cpuMemory[0x8E] = 0x80;
                    this.CPUState.cpuMemory[0x8F] = 0x80;
                    this.CPUState.cpuMemory[0x90] = 0xF0;
                    this.CPUState.cpuMemory[0x91] = 0xF0;
                    this.CPUState.cpuMemory[0x92] = 0x90;
                    this.CPUState.cpuMemory[0x93] = 0x90;
                    this.CPUState.cpuMemory[0x94] = 0xF0;
                    this.CPUState.cpuMemory[0x95] = 0xF0;

                    // 7
                    this.CPUState.cpuMemory[0x96] = 0xF0;
                    this.CPUState.cpuMemory[0x97] = 0xF0;
                    this.CPUState.cpuMemory[0x98] = 0x10;
                    this.CPUState.cpuMemory[0x99] = 0x10;
                    this.CPUState.cpuMemory[0x9A] = 0x20;
                    this.CPUState.cpuMemory[0x9B] = 0x20;
                    this.CPUState.cpuMemory[0x9C] = 0x40;
                    this.CPUState.cpuMemory[0x9D] = 0x40;
                    this.CPUState.cpuMemory[0x9E] = 0x40;
                    this.CPUState.cpuMemory[0x9F] = 0x40;

                    // 8
                    this.CPUState.cpuMemory[0xA0] = 0xF0;
                    this.CPUState.cpuMemory[0xA1] = 0xF0;
                    this.CPUState.cpuMemory[0xA2] = 0x90;
                    this.CPUState.cpuMemory[0xA3] = 0x90;
                    this.CPUState.cpuMemory[0xA4] = 0xF0;
                    this.CPUState.cpuMemory[0xA5] = 0xF0;
                    this.CPUState.cpuMemory[0xA6] = 0x90;
                    this.CPUState.cpuMemory[0xA7] = 0x90;
                    this.CPUState.cpuMemory[0xA8] = 0xF0;
                    this.CPUState.cpuMemory[0xA9] = 0xF0;

                    // 9
                    this.CPUState.cpuMemory[0xAA] = 0xF0;
                    this.CPUState.cpuMemory[0xAB] = 0xF0;
                    this.CPUState.cpuMemory[0xAC] = 0x90;
                    this.CPUState.cpuMemory[0xAD] = 0x90;
                    this.CPUState.cpuMemory[0xAE] = 0xF0;
                    this.CPUState.cpuMemory[0xAF] = 0xF0;
                    this.CPUState.cpuMemory[0xB1] = 0x10;
                    this.CPUState.cpuMemory[0xB2] = 0x10;
                    this.CPUState.cpuMemory[0xB3] = 0xF0;
                    this.CPUState.cpuMemory[0xB4] = 0xF0;

                    // A
                    this.CPUState.cpuMemory[0xB5] = 0xF0;
                    this.CPUState.cpuMemory[0xB6] = 0xF0;
                    this.CPUState.cpuMemory[0xB7] = 0x90;
                    this.CPUState.cpuMemory[0xB8] = 0x90;
                    this.CPUState.cpuMemory[0xB9] = 0xF0;
                    this.CPUState.cpuMemory[0xBA] = 0xF0;
                    this.CPUState.cpuMemory[0xBB] = 0x90;
                    this.CPUState.cpuMemory[0xBC] = 0x90;
                    this.CPUState.cpuMemory[0xBD] = 0x90;
                    this.CPUState.cpuMemory[0xBE] = 0x90;

                    // B
                    this.CPUState.cpuMemory[0xBF] = 0xE0;
                    this.CPUState.cpuMemory[0xC0] = 0xE0;
                    this.CPUState.cpuMemory[0xC1] = 0x90;
                    this.CPUState.cpuMemory[0xC2] = 0x90;
                    this.CPUState.cpuMemory[0xC3] = 0xE0;
                    this.CPUState.cpuMemory[0xC4] = 0xE0;
                    this.CPUState.cpuMemory[0xC5] = 0x90;
                    this.CPUState.cpuMemory[0xC6] = 0x90;
                    this.CPUState.cpuMemory[0xC7] = 0xE0;
                    this.CPUState.cpuMemory[0xC8] = 0xE0;

                    // C
                    this.CPUState.cpuMemory[0xC9] = 0xF0;
                    this.CPUState.cpuMemory[0xCA] = 0xF0;
                    this.CPUState.cpuMemory[0xCB] = 0x80;
                    this.CPUState.cpuMemory[0xCC] = 0x80;
                    this.CPUState.cpuMemory[0xCD] = 0x80;
                    this.CPUState.cpuMemory[0xCE] = 0x80;
                    this.CPUState.cpuMemory[0xCF] = 0x80;
                    this.CPUState.cpuMemory[0xD0] = 0x80;
                    this.CPUState.cpuMemory[0xD1] = 0xF0;
                    this.CPUState.cpuMemory[0xD2] = 0xF0;

                    // D
                    this.CPUState.cpuMemory[0xD3] = 0xE0;
                    this.CPUState.cpuMemory[0xD4] = 0xE0;
                    this.CPUState.cpuMemory[0xD5] = 0x90;
                    this.CPUState.cpuMemory[0xD6] = 0x90;
                    this.CPUState.cpuMemory[0xD7] = 0x90;
                    this.CPUState.cpuMemory[0xD8] = 0x90;
                    this.CPUState.cpuMemory[0xD9] = 0x90;
                    this.CPUState.cpuMemory[0xDA] = 0x90;
                    this.CPUState.cpuMemory[0xDB] = 0xE0;
                    this.CPUState.cpuMemory[0xDC] = 0xE0;

                    // E
                    this.CPUState.cpuMemory[0xDD] = 0xF0;
                    this.CPUState.cpuMemory[0xDE] = 0xF0;
                    this.CPUState.cpuMemory[0xDF] = 0x80;
                    this.CPUState.cpuMemory[0xE0] = 0x80;
                    this.CPUState.cpuMemory[0xE1] = 0xF0;
                    this.CPUState.cpuMemory[0xE2] = 0xF0;
                    this.CPUState.cpuMemory[0xE3] = 0x80;
                    this.CPUState.cpuMemory[0xE4] = 0x80;
                    this.CPUState.cpuMemory[0xE5] = 0xF0;
                    this.CPUState.cpuMemory[0xE6] = 0xF0;

                    // F
                    this.CPUState.cpuMemory[0xE7] = 0xF0;
                    this.CPUState.cpuMemory[0xE8] = 0xF0;
                    this.CPUState.cpuMemory[0xE9] = 0x80;
                    this.CPUState.cpuMemory[0xEA] = 0x80;
                    this.CPUState.cpuMemory[0xEB] = 0xF0;
                    this.CPUState.cpuMemory[0xEC] = 0xF0;
                    this.CPUState.cpuMemory[0xED] = 0x80;
                    this.CPUState.cpuMemory[0xEE] = 0x80;
                    this.CPUState.cpuMemory[0xEF] = 0x80;
                    this.CPUState.cpuMemory[0xF0] = 0x80;

                    this.largeCharacterMap = [0x50,0x5A,0x64,0x6E,0x78,0x82,0x8C,0x96,0xA0,0xAA,0xB5,0xBF,0xC9,0xD3,0xDD,0xE7];

                },
                setVideoMode: function(mode) {
                    // 1: Chip8, 2: SuperChip
                    this.videoMode = mode;
                    this.clearVideoMemory();
                }
            },
            mounted: function() {

                // Clear video memory
                for (let iX = 0; iX<= 64; iX++) {

                    this.videoMemory[iX] = new Array(64);

                    for (let iY = 0; iY<=64; iY++) {
                        this.videoMemory[iX][iY] = 0x0;
                    }
                }

                this.updateCanvas();
            }
        });
    </script>
</body>

</html>