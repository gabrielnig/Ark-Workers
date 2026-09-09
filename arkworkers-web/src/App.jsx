import { BrowserRouter, Routes, Route } from 'react-router-dom';

function Placeholder() {
  return <p>ArkWorkers scaffold running. Screens not yet built.</p>;
}

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="*" element={<Placeholder />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;
