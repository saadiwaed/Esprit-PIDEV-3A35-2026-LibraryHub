import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
	connect() {
		// Minimal connect handler for the "hello" controller
		// Keeps the asset mapper happy by providing a valid export
		console.log('Stimulus hello controller connected');
	}
}
