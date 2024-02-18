/**
 * UI Toasts
 */

function showToast(color,message) {
  const toastPlacementCard = document.querySelector('.toast-placement-ex')
  let selectedType, selectedPlacement, toastPlacement;

  // Dispose toast when open another
  selectedType = ['bg-primary', 'bg-danger', 'bg-info', 'bg-secondary'];
  DOMTokenList.prototype.remove.apply(toastPlacementCard.classList, selectedType);

  // Placement trigger
  selectedPlacement = ['top-0', 'end-0'];

  toastPlacementCard.classList.add(color);
  document.querySelector('.toast-body').innerHTML = message;
  DOMTokenList.prototype.add.apply(toastPlacementCard.classList, selectedPlacement);
  toastPlacement = new bootstrap.Toast(toastPlacementCard);
  toastPlacement.show();
}