document.addEventListener('DOMContentLoaded', () => {

  /* ================================
     CATEGORY RULES / FORBIDDEN TERMS
  ================================= */
  const CATEGORY_RULES = {
    clothes: 'No underwear or lingerie (panties, bras, boxers, thongs).',
    gadgets: 'No stolen or pirated electronics.',
    appliances: 'No hazardous or broken appliances.',
    furniture: 'No broken or unsafe furniture.',
    food: 'No expired or homemade perishable items that may spoil.',
    tools: 'No stolen or dangerous tools.'
  };

  const forbiddenKeywords = {
    clothes: ['underwear', 'panty', 'panties', 'bra', 'brassiere', 'brief', 'briefs', 'boxer', 'boxers', 'lingerie', 'thong', 'g-string', 'g string', 'gstring']
  };

  /* ================================
     STEP 1: CATEGORY SELECTION
  ================================= */
  const catButtons = document.querySelectorAll('.cat-btn');
  const catForm = document.querySelector('.cat-grid');
  if (catButtons.length > 0) {
    const warningDiv = document.createElement('div');
    warningDiv.id = 'categoryWarning';
    warningDiv.style.color = '#b91c1c';
    warningDiv.style.fontSize = '14px';
    warningDiv.style.marginBottom = '12px';
    warningDiv.style.minHeight = '20px';
    catForm.prepend(warningDiv);

    catButtons.forEach(btn => {
      btn.addEventListener('mouseover', () => {
        const cat = btn.value;
        warningDiv.textContent = '⚠️ Restricted items: ' + (CATEGORY_RULES[cat] ?? '');
      });
      btn.addEventListener('mouseout', () => {
        warningDiv.textContent = '';
      });

      // On category button click, submit the form
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const categoryValue = btn.value;
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'category';
        hiddenInput.value = categoryValue;
        catForm.appendChild(hiddenInput);
        catForm.submit();  // Submit the form after adding the hidden input
      });
    });
  }

  /* ================================
     STEP 2: PHOTO PREVIEW
  ================================= */
  const photoInput = document.getElementById('photos');
  const previewContainer = document.getElementById('photoPreviewContainer');

  if (photoInput && previewContainer) {
    const updatePreview = () => {
      previewContainer.innerHTML = '';  // Clear existing previews
      Array.from(photoInput.files).forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = e => {
          const box = document.createElement('div');
          box.className = 'photo-preview';
          box.style.position = 'relative';

          const img = document.createElement('img');
          img.src = e.target.result;
          box.appendChild(img);

          const removeBtn = document.createElement('button');
          removeBtn.type = 'button';
          removeBtn.className = 'remove-photo';
          removeBtn.textContent = '×';
          removeBtn.addEventListener('click', () => {
            const dt = new DataTransfer();
            Array.from(photoInput.files).forEach((f, i) => {
              if (i !== index) dt.items.add(f);  // Keep the files except the one being removed
            });
            photoInput.files = dt.files;
            updatePreview();  // Re-render the previews
          });
          box.appendChild(removeBtn);

          previewContainer.appendChild(box);  // Add the photo preview
        };
        reader.readAsDataURL(file);  // Read the file and load the preview
      });
    };

    photoInput.addEventListener('change', updatePreview);  // Trigger preview update on file input change
  }

 /* ================================
   STEP 2: FORBIDDEN KEYWORDS CHECK
================================= */
const titleInput = document.querySelector('input[name="title"]');
const descInput = document.querySelector('textarea[name="description"]');
const step2Category = document.querySelector('#postForm')?.dataset?.category;

// Only apply forbidden check if we are on Step 2 (inputs exist)
if (titleInput && descInput && step2Category) {
  let warningDiv2 = document.getElementById('forbiddenWarning');
  if (!warningDiv2) {
    warningDiv2 = document.createElement('div');
    warningDiv2.id = 'forbiddenWarning';
    warningDiv2.style.color = '#b91c1c';
    warningDiv2.style.fontSize = '14px';
    warningDiv2.style.marginBottom = '12px';
    titleInput.parentNode.insertBefore(warningDiv2, titleInput);
  }

  const checkForbidden = () => {
    // Only check if we are still on step 2 (inputs exist)
    if (!titleInput || !descInput) return;

    const text = (titleInput.value + ' ' + descInput.value).toLowerCase();
    const forbidden = forbiddenKeywords[step2Category]?.some(kw => text.includes(kw));
    if (forbidden) {
      warningDiv2.textContent = '⚠️ Your text contains restricted items/terms!';
    } else {
      warningDiv2.textContent = CATEGORY_RULES[step2Category] ? '⚠️ Restricted items: ' + CATEGORY_RULES[step2Category] : '';
    }
  };

  titleInput.addEventListener('input', checkForbidden);
  descInput.addEventListener('input', checkForbidden);
  checkForbidden();
}
  /* ================================
     STEP 3: POST NOW BUTTON ENABLE
  ================================= */
  const postButtons = document.querySelectorAll('button[type="submit"]');
  postButtons.forEach(btn => {
    btn.addEventListener('click', (e) => {
      // Prevent the default form submission if needed
      e.preventDefault();
      btn.disabled = true;
      btn.style.opacity = '0.6';
      btn.style.cursor = 'not-allowed';

      setTimeout(() => {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        document.querySelector('form').submit();  // Allow the form to submit after the delay
      }, 600);
    });
  });

});
