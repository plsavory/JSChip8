new Vue({
    el: "#app",
    data: {
        CPUState: {
            gpRegisters: [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
            pc: 0x00,
            i: 0x00,
            flags: 0x00,
            stackPointer: 0x00
        },
        FlagsBitfieldSpec: {
            "bit": 0,
            "name": "OVERFLOW",
        }
    },
    methods: {
        displayHex: function(number) {
            if (number < 0)
            {
                number = 0xFFFFFFFF + number + 1;
            }

            return "0x" + number.toString(16).toUpperCase();
        },
        displayRegisterID: function(number) {
            if (number < 0)
            {
                number = 0xFFFFFFFF + number + 1;
            }

            return number.toString(16).toUpperCase();
        },
        resetCPU: function () {
            console.log("Resetting CPU...");
            this.reset();
        },
        stepCPU: function () {
            console.log("Stepping CPU...");
        },

        loadROM: function () {
            console.log("Loading ROM...");
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

            this.CPUState.stackPointer = 0xFF;
        },
        writeVRegister: function (registerID, value) {
            // Write to an 8-bit register

            // Can't write to VF or higher
            if (registerID > 0xE) {
                return;
            }

            this.CPUState.gpRegisters[registerID] = value & 255;
        }
    }
});