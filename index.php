<?php

// This is a nasty/quick solution - would be nice to have a file selector in JavaScript or something like that later.
if (isset($_GET['romFilename'])) {
    $filename = "roms/" . $_GET['romFilename'];
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
	<title>Chip-8 Interpreter</title>
	<link rel="stylesheet" type="text/css" href="bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="style.css">
</head>

<body>

    <div id="app">
        <div class="container">
            <div class="row">
                <div class="col-sm-12">
                    <h1 style="text-align:center">Chip-8 JavaScript Interpreter</h1>
                    <hr/>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <h1 style="text-align: center">Display</h1>
                    <hr>

                    <div class="row" style="height:270px; border-style:inset">
                        <div class="col-sm-7">
                            <canvas id="canvas" width="512" height="256"></canvas>
                        </div>

                        <div class="col-sm-5">
                            <h2>Stack</h2>
                            <div class="row">
                                <div class="col-sm-12" style="border-style:inset">
                                    <div v-for="(item, index) in CPUState.stack">
                                        <p style="float:left; border-style:inset; padding:3px;">
                                            {{displayHex(index)}}: {{displayHex(item)}}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-4 offset-4" style="border-style:inset;">
                    <h2 style="text-align:center">Clock Speed</h2>
                    <div>
                        <input type="range" min="1" max="50" v-model="clockSpeed"/>
                        <p>{{clockSpeed}}</p>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="row">
                        <div class="col-sm-5">
                            <h2 style="text-align:center">Activity Log</h2>
                            <hr/>
                            <div class="row"  style="height: 900px; overflow:scroll; border-style:inset">
                                <div class="col-sm-12">
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
                        </div>

                        <div class="col-sm-7">
                            <h2 style="text-align:center">Current CPU State</h2>
                            <hr/>

                            <div class="row">
                                <div class="col-sm-12" style="border-style:inset">
                                    <p style="text-align: center">
                                        Controls
                                    </p>

                                    <div class="row">
                                        <div class="col-sm-3">
                                            <button v-on:click="toggleMemoryDisplay">Mem Display</button>
                                        </div>

                                        <div class="col-sm-3">
                                            <button v-on:click="resetCPU">Reset</button>
                                        </div>

                                        <div class="col-sm-3">
                                            <button v-on:click="stepCPU">Step</button>
                                        </div>

                                        <div class="col-sm-3">
                                            <div v-if="CPUState.state === 'running'">
                                                <button v-if="" v-on:click="breakCPU">Break</button>
                                            </div>
                                            <div v-else>
                                                <button v-if="" v-on:click="runCPU">Run</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-6" style="border-style:inset">
                                    <p style="text-align: center">Registers</p>
                                    <hr/>

                                    <p>Program Counter: {{displayHex(CPUState.pc)}}</p>

                                    <p>Index: {{displayHex(CPUState.i)}}</p>

                                    <p>Stack Pointer: {{displayHex(CPUState.stackPointer)}}</p>

                                    <p v-for="(item, index) in CPUState.gpRegisters">
                                        V{{displayRegisterID(index)}}: {{displayHex(item)}}
                                    </p>
                                </div>

                                <div class="col-sm-6" style="border-style:inset">
                                    <p style="text-align: center">Memory</p>
                                    <hr/>

                                    <span v-if="displayMemory" v-html="getMemoryDisplay()"></span>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="vue.js"></script>

    <script>

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
                    delayTimer: 0
                },
                programData: [
                    <?=$binaryString;?>
                ],
                memoryDisplayHtml: "",
                CPULog: [],
                videoMemory: [], // 64x64 Pixels
                clockSpeed: 1,
                displayMemory: true,
                keyboardMap: []
            },
            methods: {
                updateCanvas: function() {
                    var canvas = document.getElementById('canvas');
                    ctx = canvas.getContext('2d');

                    ctx.clearRect(0,0,canvas.width,canvas.height);
                    ctx.fillStyle = "black";

                    ctx.fillRect(0, 0, canvas.width, canvas.height);

                    // Update the canvas with the contents of vide memory
                    ctx.fillStyle="green";

                    for (var iX = 0; iX<64;iX++) {
                        for (var iY = 0; iY<64; iY++) {
                            if (this.videoMemory[iX][iY]) {
                                ctx.fillRect(iX*8, iY*8,8, 8);
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

                    var displayString = "<table>";

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
                    for (var i = 0; i<16;i++) {
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
                    for (var i = 0; i<=0xF;i++) {
                        this.CPUState.stack[i] = 0x0;
                    }

                    // Clear the keyboard map
                    for (var i = 0; i<=0xF;i++) {
                        this.keyboardMap[i] = 0x0;
                    }

                    // Load the program into memory
                    for (var i = 0; i<=0xFFF; i++) {

                        if (i >= 0x200) {
                            this.CPUState.cpuMemory[i] = this.programData[i-0x200];
                        }

                    }

                    // Clear video memory
                    for (var iX = 0; iX<= 64; iX++) {

                        this.videoMemory[iX] = new Array(64);

                        for (var iY = 0; iY<=64; iY++) {
                            this.videoMemory[iX][iY] = 0x0;
                        }
                    }

                    this.updateCanvas();

                    this.clockSpeed = 1;

                    this.CPULog = [];
                },
                writeVRegister: function (registerID, value) {
                    // Write to an 8-bit register

                    // Can't write to VF or higher
                    if (registerID > 0xE) {
                        return;
                    }

                    this.CPUState.gpRegisters[registerID] = value & 255;
                },
                writeVFRegister: function(value) {
                    this.CPUState.gpRegisters[0xF] = value & 255;
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
                                case 0x2:
                                    this.vandHandler(opcode);
                                    break;
                                case 0x4:
                                    this.addRegHandler(opcode);
                                    break;
                                case 0x6:
                                    this.shrHandler(opcode);
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
                                case 0x15:
                                    this.setDelayTimer(opcode);
                                    break;
                                case 0x1E:
                                    this.addIHandler(opcode);
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

                    if (this.CPUState.state === "running") {
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

                    for (var iY = 0; iY < height; iY++) {
                        // Draw a line of the sprite
                        var spriteBitmapLine = this.CPUState.cpuMemory[this.CPUState.i+iY];

                        for (var iX=0; iX < 8; iX++) {
                            var mask = 1 << (7-iX);

                            if ((spriteBitmapLine & mask) !== 0) {
                                this.videoMemory[(x + iX) % 64][(y + iY) % 64] ^= 1;
                            } else {
                                this.videoMemory[(x + iX) % 64][(y + iY) % 64] ^= 0;
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
                    for (var iX = 0; iX<= 64; iX++) {

                        this.videoMemory[iX] = new Array(64);

                        for (var iY = 0; iY<=64; iY++) {
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
                getDelayTimer: function(opcode) {
                    // Store the contents of delayTimer into VX
                    let register = this.getRegisterId(opcode.whole);

                    this.writeVRegister(register,this.CPUState.delayTimer);
                },
                fillRegisters: function(opcode) {
                    // Read registers V0 through Vx from memory starting at location I.
                    var registerCount = this.getRegisterId(opcode.whole);
                    for (var i = 0; i <= registerCount; i++) {
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
                  var register = this.getRegisterId(opcode.whole);

                  if (this.bitTest(this.CPUState.gpRegisters[register],7)) {
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
                shlHandler: function(opcode) {
                    // Set Vx = Vx SHL 1.
                    var register = this.getRegisterId(opcode.whole);

                    if (this.bitTest(this.CPUState.gpRegisters[register],0)) {
                        this.writeVFRegister(1);
                    } else {
                        this.writeVFRegister(0);
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

                    var register1 = this.getRegisterId(opcode.whole);
                    var register2 = this.get2ndRegisterId(opcode.whole);

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
                    this.CPUState.i = this.CPUState.i + this.CPUState.gpRegisters[register];

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

                    this.writeVRegister(register1,result & 0xFF);

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "ADDREG",
                        arg: this.displayRegisterID(register1),
                        message: this.displayRegisterID(register2)
                    });
                },
                skipIfKeyPressed: function(opcode) {
                    let key = this.CPUState.gpRegisters[this.getRegisterId(opcode.whole)];

                    if (this.keyboardMap[key] === 1) {
                        this.CPUState.pc += 2;
                    }

                    this.logActivity({
                        opcodeVal: opcode.whole,
                        mnemonic: "SKNP",
                        arg: "",
                        message: ""
                    });
                },
                skipIfKeyNotPressed: function(opcode) {
                    let key = this.CPUState.gpRegisters[this.getRegisterId(opcode.whole)];

                    if (this.keyboardMap[key] === 0) {
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
                }
            },
            mounted: function() {

                // Clear video memory
                for (var iX = 0; iX<= 64; iX++) {

                    this.videoMemory[iX] = new Array(64);

                    for (var iY = 0; iY<=64; iY++) {
                        this.videoMemory[iX][iY] = 0x0;
                    }
                }

                this.updateCanvas();
            }
        });
    </script>
</body>

</html>