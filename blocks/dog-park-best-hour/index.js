"use strict";

const { registerBlockType } = wp.blocks;

// Import edit and save functions from separate files
import edit from './edit';
import save from './save';

// Register the block
registerBlockType('dog-park/best-hour', {
    edit,
    save,
});