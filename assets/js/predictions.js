(() => {
  const form = document.getElementById('predictionForm');
  if (!form) return;
  const samples = JSON.parse(document.getElementById('demoSamples').textContent);
  const error = document.getElementById('predictionError');
  const result = document.getElementById('predictionResult');
  const placeholder = document.getElementById('predictionPlaceholder');
  const invalidate = () => { result.hidden = true; placeholder.hidden = false; error.textContent = ''; };
  document.getElementById('demoSample').addEventListener('change', (event) => {
    const sample = samples[Number(event.target.value)];
    if (!sample) return;
    form.querySelectorAll('[data-feature]').forEach(input => { input.value = sample.features[input.dataset.feature]; });
    invalidate();
  });
  form.addEventListener('input', invalidate);
  form.addEventListener('submit', async event => {
    event.preventDefault();
    invalidate();
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    const features = {};
    form.querySelectorAll('[data-feature]').forEach(input => { features[input.dataset.feature] = Number(input.value); });
    try {
      const response = await fetch('api/predict.php', { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ csrf: form.elements.csrf.value, features }) });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Prediction failed. Please try again.');
      document.getElementById('predictedOutcome').textContent = data.outcome;
      document.getElementById('predictedIndicator').textContent = data.indicator;
      document.getElementById('predictedRecommendation').textContent = data.recommendation;
      const shares = document.getElementById('predictedShares');
      shares.replaceChildren();
      Object.entries(data.probabilities).forEach(([label, share]) => {
        const item = document.createElement('li'); item.textContent = `${label}: ${(share * 100).toFixed(1)}%`; shares.append(item);
      });
      result.hidden = false; placeholder.hidden = true;
    } catch (failure) { error.textContent = failure.message || 'Prediction unavailable. Please reload and try again.'; }
    finally { button.disabled = false; }
  });
})();
