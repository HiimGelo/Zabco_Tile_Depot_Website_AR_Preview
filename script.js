let hitTestSource = null;
let hitTestSourceRequested = false;
const placedPoints = [];

const geometry = new THREE.SphereGeometry(0.01, 16, 16);
const material = new THREE.MeshBasicMaterial({ color: 0xff0000 });

renderer.setAnimationLoop((timestamp, frame) => {
  if (frame) {
    const referenceSpace = renderer.xr.getReferenceSpace();
    const session = renderer.xr.getSession();

    if (!hitTestSourceRequested) {
      session.requestReferenceSpace('viewer').then((refSpace) => {
        session.requestHitTestSource({ space: refSpace }).then((source) => {
          hitTestSource = source;
        });
      });
      hitTestSourceRequested = true;
    }

    if (hitTestSource) {
      const hitTestResults = frame.getHitTestResults(hitTestSource);

      if (hitTestResults.length) {
        const hit = hitTestResults[0];
        const pose = hit.getPose(referenceSpace);

        // Show reticle or place marker
        reticle.visible = true;
        reticle.matrix.fromArray(pose.transform.matrix);
      }
    }
  }

  renderer.render(scene, camera);
});

// Tap to place points
renderer.domElement.addEventListener('click', () => {
  if (reticle.visible) {
    const point = new THREE.Mesh(geometry, material);
    point.position.setFromMatrixPosition(reticle.matrix);
    scene.add(point);
    placedPoints.push(point);

    if (placedPoints.length >= 2) {
      const p1 = placedPoints[placedPoints.length - 2].position;
      const p2 = placedPoints[placedPoints.length - 1].position;

      const lineGeo = new THREE.BufferGeometry().setFromPoints([p1, p2]);
      const lineMat = new THREE.LineBasicMaterial({ color: 0x00ff00 });
      const line = new THREE.Line(lineGeo, lineMat);
      scene.add(line);

      const distance = p1.distanceTo(p2);
      console.log(`Distance: ${distance.toFixed(2)} meters`);

      // Optional: Add 3D text or label to show the distance
    }
  }
});