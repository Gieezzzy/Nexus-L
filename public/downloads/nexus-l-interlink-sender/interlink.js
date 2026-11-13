// On InterLink pages: asks the sender whether this tab has a job to continue (the form posts back between steps).
chrome.runtime.sendMessage({ type: 'ready' });
